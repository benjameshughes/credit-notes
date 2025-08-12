<?php

// app/Jobs/ProcessCsvToPdf.php

namespace App\Jobs;

use App\Events\BatchCompleted;
use App\Models\PdfGenerationJob;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Barryvdh\DomPDF\Facade\Pdf;

class ProcessCsvToPdf implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 3600; // 1 hour timeout

    public $tries = 3; // Retry up to 3 times

    public $backoff = [60, 300, 900]; // Wait 1 minute, 5 minutes, then 15 minutes between retries

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
            // Check if this batch is paused before starting processing
            if (cache()->has("batch_paused_{$job->batch_id}")) {
                // Batch is paused - mark this job as paused and don't process
                $job->update(['status' => 'paused']);
                \Log::info("Job {$job->id} paused due to batch pause flag for batch {$job->batch_id}");
                return;
            }
            
            $job->update(['status' => 'processing']);
            
            // Add a small delay to simulate processing time for testing pause functionality
            sleep(2);

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
            
            // Check if this was the last job to complete
            $this->checkBatchCompletion($job);

        } catch (\Exception $e) {
            // Ensure status is always updated on exception
            try {
                $job->update([
                    'processed_rows' => 0,
                    'failed_rows' => 1,
                    'status' => 'failed',
                ]);
                
                // Check if this was the last job even though it failed
                $this->checkBatchCompletion($job);
            } catch (\Exception $updateException) {
                Log::error('Failed to update job status after exception: '.$updateException->getMessage());
            }

            Log::error("PDF generation failed for job {$this->jobId}: ".$e->getMessage());
        }
    }

    private function generatePdf($data, $index, $batchId)
    {
        try {
            Log::info("Starting PDF generation for job {$this->jobId}, batch {$batchId}, index {$index}");
            Log::info("PDF data: " . json_encode($data));
            
            // Create filename using credit note number or fallback
            $filename = 'credit_note_';
            if (! empty($data['reference'])) {
                $filename .= $data['reference'];
            } else {
                $filename .= ($index + 1);
            }
            $filename .= '.pdf';

            $path = 'pdfs/'.$batchId.'/'.$filename;
            $fullPath = storage_path('app/'.$path);

            Log::info("PDF will be saved to: {$fullPath}");

            // Ensure directory exists
            $directory = dirname($fullPath);
            if (! is_dir($directory)) {
                Log::info("Creating directory: {$directory}");
                mkdir($directory, 0755, true);
            }

            // Use DomPDF to generate PDF
            Log::info("Calling DomPDF generation...");
            
            $pdf = Pdf::loadView('pdf.credit-note-template', compact('data'))
                ->setPaper('a4', 'portrait');
            
            $pdf->save($fullPath);

            Log::info("PDF generated successfully: {$path}");
            return $path;

        } catch (\Exception $e) {
            Log::error('PDF generation error: ' . $e->getMessage());
            Log::error('PDF generation stack trace: ' . $e->getTraceAsString());

            return null;
        }
    }

    private function checkBatchCompletion($job)
    {
        // Simple check - count remaining jobs
        $totalJobs = PdfGenerationJob::where('batch_id', $job->batch_id)->count();
        $completedJobs = PdfGenerationJob::where('batch_id', $job->batch_id)
            ->whereIn('status', ['completed', 'failed'])
            ->count();
            
        Log::info("Batch {$job->batch_id}: {$completedJobs}/{$totalJobs} jobs completed");
        
        // If all jobs are complete, dispatch the batch completed event
        if ($completedJobs >= $totalJobs) {
            // Check if batch is already processed (has download paths) 
            $alreadyProcessed = PdfGenerationJob::where('batch_id', $job->batch_id)
                ->whereNotNull('zip_path')
                ->exists();
                
            if (!$alreadyProcessed) {
                Log::info("Batch {$job->batch_id} is complete - dispatching BatchCompleted event");
                BatchCompleted::dispatch($job->batch_id, $job->user_id);
            } else {
                Log::info("Batch {$job->batch_id} already processed");
            }
        }
    }

}
