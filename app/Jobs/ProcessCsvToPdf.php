<?php

// app/Jobs/ProcessCsvToPdf.php

namespace App\Jobs;

use App\Models\PdfGenerationJob;
use Spatie\LaravelPdf\Facades\Pdf;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use ZipArchive;

class ProcessCsvToPdf implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 3600; // 1 hour timeout

    public function __construct(
        public int $jobId,
        public array $rowData,
        public int $rowIndex
    ) {}

    public function handle()
    {
        $job = PdfGenerationJob::find($this->jobId);

        if (! $job) {
            return;
        }

        try {
            $job->update(['status' => 'processing']);

            // Generate single PDF for this row
            $pdfPath = $this->generatePdf($this->rowData, $this->rowIndex, $job->batch_id);

            if ($pdfPath) {
                $job->update([
                    'processed_rows' => 1,
                    'failed_rows' => 0,
                    'status' => 'completed',
                    'file_paths' => json_encode([$pdfPath]),
                ]);
            } else {
                $job->update([
                    'processed_rows' => 0,
                    'failed_rows' => 1,
                    'status' => 'failed',
                ]);
            }

        } catch (\Exception $e) {
            // Ensure status is always updated on exception
            try {
                $job->update([
                    'processed_rows' => 0,
                    'failed_rows' => 1,
                    'status' => 'failed',
                ]);
            } catch (\Exception $updateException) {
                Log::error('Failed to update job status after exception: '.$updateException->getMessage());
            }

            Log::error("PDF generation failed for job {$this->jobId}: ".$e->getMessage());
        } finally {
            // Always check batch completion
            try {
                $this->checkBatchCompletion($job->batch_id);
            } catch (\Exception $e) {
                Log::error("Error in finally block for job {$this->jobId}: ".$e->getMessage());
            }
        }
    }

    private function generatePdf($data, $index, $batchId)
    {
        try {
            // Create filename using credit note number or fallback
            $filename = 'credit_note_';
            if (! empty($data['Number'])) {
                $filename .= Str::slug($data['Number']);
            } else {
                $filename .= ($index + 1);
            }
            $filename .= '.pdf';

            $path = 'pdfs/'.$batchId.'/'.$filename;
            $fullPath = storage_path('app/'.$path);

            // Ensure directory exists
            $directory = dirname($fullPath);
            if (!is_dir($directory)) {
                mkdir($directory, 0755, true);
            }

            // Use Spatie Laravel PDF to generate PDF
            Pdf::view('pdf.credit-note-template', compact('data'))
                ->format('a4')
                ->save($fullPath);

            return $path;

        } catch (\Exception $e) {
            Log::error('PDF generation error: '.$e->getMessage());

            return null;
        }
    }

    private function checkBatchCompletion($batchId)
    {
        // Use database locking to prevent race conditions
        DB::transaction(function () use ($batchId) {
            $jobs = PdfGenerationJob::where('batch_id', $batchId)
                ->lockForUpdate()
                ->get();

            $completedJobs = $jobs->where('status', 'completed');
            $failedJobs = $jobs->where('status', 'failed');
            $processingJobs = $jobs->whereIn('status', ['processing', 'pending']);

            // If all jobs are either completed or failed (none processing or pending)
            if (($completedJobs->count() + $failedJobs->count()) === $jobs->count() && $processingJobs->count() === 0) {
                // Check if we've already processed this batch
                $alreadyProcessed = $jobs->whereNotNull('zip_path')->count() > 0 || $jobs->whereNotNull('single_pdf_path')->count() > 0;

                if (! $alreadyProcessed) {
                    $pdfPaths = [];

                    foreach ($completedJobs as $job) {
                        $filePaths = json_decode($job->file_paths, true);
                        if ($filePaths) {
                            $pdfPaths = array_merge($pdfPaths, $filePaths);
                        }
                    }

                    if (! empty($pdfPaths)) {
                        if (count($pdfPaths) > 1) {
                            // Multiple PDFs - create ZIP file
                            $zipPath = $this->createZipFile($pdfPaths, $jobs->first()->original_filename);

                            // Update all jobs in batch with the zip path
                            PdfGenerationJob::where('batch_id', $batchId)
                                ->update(['zip_path' => $zipPath]);

                            // Clean up individual PDF files after zipping
                            $this->cleanup($pdfPaths);
                        } else {
                            // Single PDF - keep the individual PDF file
                            $singlePdfPath = $pdfPaths[0];

                            // Update all jobs in batch with the single PDF path
                            PdfGenerationJob::where('batch_id', $batchId)
                                ->update(['single_pdf_path' => $singlePdfPath]);
                        }
                    }
                }
            }
        });
    }


    private function createZipFile($pdfPaths, $originalFilename)
    {
        $zipFilename = 'pdfs_'. Str::slug(pathinfo($originalFilename, PATHINFO_FILENAME)).'_'.date('Y-m-d_H-i-s').'.zip';
        $zipPath = 'downloads/'.$zipFilename;
        $fullZipPath = storage_path('app/'.$zipPath);

        // Ensure directory exists
        $dir = dirname($fullZipPath);
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $zip = new ZipArchive;

        if ($zip->open($fullZipPath, ZipArchive::CREATE) === true) {
            foreach ($pdfPaths as $pdfPath) {
                $fullPdfPath = Storage::path($pdfPath);
                if (file_exists($fullPdfPath)) {
                    $zip->addFile($fullPdfPath, basename($pdfPath));
                }
            }
            $zip->close();

            return $zipPath;
        }

        throw new \Exception('Could not create zip file');
    }

    private function cleanup($pdfPaths)
    {
        foreach ($pdfPaths as $path) {
            Storage::delete($path);
        }
    }
}
