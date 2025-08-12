<?php

namespace App\Livewire;

use App\Jobs\ProcessCsvToPdf;
use App\Models\PdfGenerationJob;
use Illuminate\Support\Str;
use Livewire\Component;
use Livewire\WithFileUploads;

class CsvToPdfProcessor extends Component
{
    use WithFileUploads;

    public $csvFile;

    public $processingJobs = [];

    public $completedJobs = [];

    public $isProcessing = false;

    public $toasts = [];

    public $showMapping = false;

    public $csvHeaders = [];

    public $fieldMapping = [];

    public $csvPreview = [];

    protected $rules = [
        'csvFile' => 'required|file|mimes:csv|max:10240', // 10MB max
    ];

    const MAX_CSV_ROWS = 1000; // Maximum CSV rows allowed

    public function mount()
    {
        $this->loadJobs();
    }

    public function uploadCsv()
    {
        $this->validate();

        try {
            $this->isProcessing = true;

            // Get the real path from Livewire's temporary file
            $fullPath = $this->csvFile->getRealPath();

            // Parse CSV data
            $csvData = $this->parseCsv($fullPath);

            if (empty($csvData)) {
                throw new \Exception('CSV file is empty or contains no valid data');
            }

            // Check row limit
            if (count($csvData) > self::MAX_CSV_ROWS) {
                throw new \Exception('CSV file contains too many rows. Maximum allowed: '.self::MAX_CSV_ROWS.' rows.');
            }

            // Store CSV data for mapping
            $this->csvHeaders = array_keys($csvData[0]);
            $this->csvPreview = array_slice($csvData, 0, 3); // First 3 rows for preview

            // Initialize field mapping with smart defaults
            $this->fieldMapping = $this->getSmartMapping($this->csvHeaders);

            // Store CSV data in session for later processing (before reset)
            $filename = $this->csvFile->getClientOriginalName();
            session(['csv_data' => $csvData, 'csv_filename' => $filename]);

            // Show mapping interface
            $this->showMapping = true;
            $this->reset('csvFile');

        } catch (\Exception $e) {
            $this->addToast('error', 'Upload failed: '.$e->getMessage());
        } finally {
            $this->isProcessing = false;
        }
    }

    private function parseCsv($filePath)
    {
        $data = [];

        if (! file_exists($filePath)) {
            throw new \Exception('File not found');
        }

        $handle = fopen($filePath, 'r');
        if (! $handle) {
            throw new \Exception('Cannot read file');
        }

        $headers = fgetcsv($handle);
        if (! $headers) {
            fclose($handle);
            throw new \Exception('CSV file is empty or has invalid format');
        }

        while (($row = fgetcsv($handle)) !== false) {
            if (count($row) === count($headers)) {
                $data[] = array_combine($headers, $row);
            }
        }

        fclose($handle);

        return $data;
    }

    public function loadJobs()
    {
        // Group jobs by batch_id and calculate batch-level progress - only for current user
        $batches = PdfGenerationJob::where('user_id', auth()->id())
            ->select('batch_id', 'original_filename')
            ->selectRaw('MIN(created_at) as created_at')
            ->selectRaw('COUNT(*) as total_rows')
            ->selectRaw('SUM(CASE WHEN status = "completed" THEN 1 ELSE 0 END) as completed_rows')
            ->selectRaw('SUM(CASE WHEN status = "failed" THEN 1 ELSE 0 END) as failed_rows')
            ->selectRaw('SUM(CASE WHEN status = "processing" THEN 1 ELSE 0 END) as processing_rows')
            ->selectRaw('COALESCE(MAX(zip_path), "") as zip_path')
            ->selectRaw('COALESCE(MAX(single_pdf_path), "") as single_pdf_path')
            ->selectRaw('COALESCE(MAX(download_status), "pending") as download_status')
            ->groupBy('batch_id', 'original_filename')
            ->orderBy('created_at', 'desc')
            ->take(20)
            ->get();

        $allJobs = $batches->map(function ($batch) {
            $completedCount = $batch->completed_rows;
            $failedCount = $batch->failed_rows;
            $processingCount = $batch->processing_rows;
            $totalCount = $batch->total_rows;

            // Determine overall batch status based on job completion and download status
            if ($completedCount + $failedCount === $totalCount) {
                // All jobs are done, but check download status
                if ($batch->download_status === 'creating_download') {
                    $status = 'packaging';
                } elseif ($batch->download_status === 'ready') {
                    $status = $failedCount > 0 ? 'completed_with_errors' : 'completed';
                } elseif ($batch->download_status === 'failed') {
                    $status = 'download_failed';
                } else {
                    // Jobs are done but download hasn't started yet - show packaging state
                    $status = 'packaging';
                }
            } elseif ($processingCount > 0 || $completedCount > 0) {
                $status = 'processing';
            } else {
                $status = 'pending';
            }

            $progress = $totalCount > 0 ? round((($completedCount + $failedCount) / $totalCount) * 100, 1) : 0;

            return [
                'batch_id' => $batch->batch_id,
                'filename' => $batch->original_filename,
                'total_rows' => $totalCount,
                'completed_rows' => $completedCount,
                'failed_rows' => $failedCount,
                'processing_rows' => $processingCount,
                'status' => $status,
                'download_status' => $batch->download_status,
                'progress' => $progress,
                'zip_path' => $batch->zip_path,
                'single_pdf_path' => $batch->single_pdf_path,
                'download_available' => $batch->download_status === 'ready' && (!empty($batch->zip_path) || !empty($batch->single_pdf_path)),
                'is_single_pdf' => empty($batch->zip_path) && ! empty($batch->single_pdf_path),
                'created_at' => $batch->created_at->format('M j, Y g:i A'),
            ];
        });

        // Separate processing and completed jobs
        $this->processingJobs = $allJobs->whereIn('status', ['pending', 'processing', 'packaging'])->values()->toArray();
        $this->completedJobs = $allJobs->whereIn('status', ['completed', 'completed_with_errors', 'download_failed'])->values()->toArray();
    }

    private function addToast($type, $text)
    {
        $this->toasts[] = [
            'id' => uniqid(),
            'type' => $type,
            'message' => $text,
            'timestamp' => time()
        ];
    }

    public function removeToast($toastId)
    {
        $this->toasts = array_filter($this->toasts, function($toast) use ($toastId) {
            return $toast['id'] !== $toastId;
        });
    }

    private function getSmartMapping($headers)
    {
        $mapping = [];

        foreach ($headers as $header) {
            $lower = strtolower($header);

            if (str_contains($lower, 'reference') && ! str_contains($lower, 'customer')) {
                $mapping[$header] = 'reference';
            } elseif (str_contains($lower, 'customer')) {
                $mapping[$header] = 'customer';
            } elseif (str_contains($lower, 'date')) {
                $mapping[$header] = 'date';
            } elseif (str_contains($lower, 'type')) {
                $mapping[$header] = 'type';
            } elseif (str_contains($lower, 'net') || str_contains($lower, 'amount')) {
                $mapping[$header] = 'net';
            } elseif (str_contains($lower, 'vat') || str_contains($lower, 'tax')) {
                $mapping[$header] = 'vat';
            } elseif (str_contains($lower, 'total')) {
                $mapping[$header] = 'total';
            } elseif (str_contains($lower, 'detail') || str_contains($lower, 'description')) {
                $mapping[$header] = 'details';
            } else {
                $mapping[$header] = ''; // No mapping
            }
        }

        return $mapping;
    }

    public function confirmMapping()
    {

        try {
            $csvData = session('csv_data');
            $filename = session('csv_filename');

            if (! $csvData || ! $filename) {
                throw new \Exception('CSV data not found. Please upload the file again.');
            }

            $this->isProcessing = true;

            // Create individual job records for each CSV row
            $batchId = (string) Str::uuid();

            foreach ($csvData as $index => $rowData) {
                // Apply field mapping to transform data
                $mappedData = $this->applyFieldMapping($rowData);

                $job = PdfGenerationJob::create([
                    'batch_id' => $batchId,
                    'original_filename' => $filename,
                    'total_rows' => 1, // Each job handles one row
                    'processed_rows' => 0,
                    'failed_rows' => 0,
                    'status' => 'pending',
                    'row_data' => json_encode($mappedData), // Store the mapped data
                    'row_index' => $index,
                    'user_id' => auth()->id(),
                ]);

                // Dispatch individual processing job with mapped data
                ProcessCsvToPdf::dispatch($job->id, $mappedData, $index);
            }

            $this->addToast('success', 'CSV uploaded! Processing '.count($csvData).' records...');
            $this->showMapping = false;
            $this->reset(['csvHeaders', 'fieldMapping', 'csvPreview']);
            $this->loadJobs();

            // Clear session data
            session()->forget(['csv_data', 'csv_filename']);

        } catch (\Exception $e) {
            $this->addToast('error', 'Processing failed: '.$e->getMessage());
        } finally {
            $this->isProcessing = false;
        }
    }

    private function applyFieldMapping($rowData)
    {
        $mappedData = [];

        foreach ($this->fieldMapping as $csvHeader => $pdfField) {
            if ($pdfField && isset($rowData[$csvHeader])) {
                $mappedData[$pdfField] = $rowData[$csvHeader];
            }
        }

        return $mappedData;
    }

    public function deleteJob($batchId)
    {
        try {
            // Get all jobs in the batch for the current user
            $jobs = PdfGenerationJob::where('batch_id', $batchId)
                ->where('user_id', auth()->id())
                ->get();

            if ($jobs->isEmpty()) {
                $this->addToast('error', 'Job not found or you do not have permission to delete it.');
                return;
            }

            // Check authorization for deletion using policy
            $firstJob = $jobs->first();
            if (! auth()->user()->can('delete', $firstJob)) {
                $this->addToast('error', 'Cannot delete job while it is still processing.');
                return;
            }

            // Delete all jobs in the batch (this will also delete associated files)
            foreach ($jobs as $job) {
                if (auth()->user()->can('delete', $job)) {
                    $job->delete();
                }
            }

            $this->addToast('success', 'Job and associated files deleted successfully.');
            $this->loadJobs();

        } catch (\Exception $e) {
            $this->addToast('error', 'Failed to delete job: '.$e->getMessage());
        }
    }

    /**
     * Note: Real-time updates work via polling instead of WebSockets
     * The wire:poll in the Blade template will automatically show status changes
     * as the download_status field gets updated by our event listeners
     */

    public function render()
    {
        return view('livewire.csv-to-pdf-processor')
            ->layout('components.layouts.app');
    }
}
