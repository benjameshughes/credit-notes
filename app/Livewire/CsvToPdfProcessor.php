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

    public $message = '';

    public $messageType = '';

    public $activeBatches = []; // Track active batch IDs for real-time updates

    protected $rules = [
        'csvFile' => 'required|file|mimes:csv,txt|max:10240',
    ];

    public function mount()
    {
        $this->loadJobs();

        // Track active batches for real-time updates (ensure strings for Livewire compatibility)
        $this->activeBatches = collect($this->processingJobs)
            ->pluck('batch_id')
            ->map(fn ($id) => (string) $id)
            ->toArray();
    }

    public function uploadCsv()
    {
        $this->resetMessages();
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

            // Create individual job records for each CSV row
            $batchId = (string) Str::uuid(); // Convert to string for Livewire compatibility
            $jobIds = [];

            foreach ($csvData as $index => $rowData) {
                $job = PdfGenerationJob::create([
                    'batch_id' => $batchId,
                    'original_filename' => $this->csvFile->getClientOriginalName(),
                    'total_rows' => 1, // Each job handles one row
                    'processed_rows' => 0,
                    'failed_rows' => 0,
                    'status' => 'pending',
                    'row_data' => json_encode($rowData), // Store the row data
                    'row_index' => $index,
                    'user_id' => auth()->id(),
                ]);

                $jobIds[] = $job->id;

                // Dispatch individual processing job
                ProcessCsvToPdf::dispatch($job->id, $rowData, $index);
            }

            $this->setMessage('success', 'CSV uploaded! Processing '.count($csvData).' records...');
            $this->reset('csvFile');
            $this->loadJobs();

            // Add new batch to active batches for real-time updates (ensure string)
            $this->activeBatches[] = $batchId;

            // Emit event to frontend to subscribe to new batch
            $this->dispatch('batchCreated', $batchId);

        } catch (\Exception $e) {
            $this->setMessage('error', 'Upload failed: '.$e->getMessage());
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
            throw new \Exception('Invalid CSV format');
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
            ->selectRaw('MAX(zip_path) as zip_path')
            ->selectRaw('MAX(single_pdf_path) as single_pdf_path')
            ->groupBy('batch_id', 'original_filename')
            ->orderBy('created_at', 'desc')
            ->take(20)
            ->get();

        $allJobs = $batches->map(function ($batch) {
            $completedCount = $batch->completed_rows;
            $failedCount = $batch->failed_rows;
            $processingCount = $batch->processing_rows;
            $totalCount = $batch->total_rows;

            // Determine overall batch status
            if ($completedCount + $failedCount === $totalCount) {
                $status = $failedCount > 0 ? 'completed_with_errors' : 'completed';
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
                'progress' => $progress,
                'zip_path' => $batch->zip_path,
                'single_pdf_path' => $batch->single_pdf_path,
                'download_available' => $batch->zip_path || $batch->single_pdf_path,
                'is_single_pdf' => ! $batch->zip_path && $batch->single_pdf_path,
                'created_at' => $batch->created_at->format('M j, Y g:i A'),
            ];
        });

        // Separate processing and completed jobs
        $this->processingJobs = $allJobs->whereIn('status', ['pending', 'processing'])->values()->toArray();
        $this->completedJobs = $allJobs->whereIn('status', ['completed', 'completed_with_errors'])->values()->toArray();
    }

    public function updateJobProgress($batchId, $data)
    {
        // Find and update the specific job in processing jobs array
        $jobIndex = collect($this->processingJobs)->search(function ($job) use ($batchId) {
            return $job['batch_id'] === $batchId;
        });

        if ($jobIndex !== false) {
            $this->processingJobs[$jobIndex] = array_merge($this->processingJobs[$jobIndex], $data);

            // Move to completed jobs if finished
            if (in_array($data['status'], ['completed', 'completed_with_errors'])) {
                $completedJob = $this->processingJobs[$jobIndex];
                array_unshift($this->completedJobs, $completedJob); // Add to beginning of completed
                unset($this->processingJobs[$jobIndex]); // Remove from processing
                $this->processingJobs = array_values($this->processingJobs); // Re-index array

                // Remove from active batches
                $this->activeBatches = array_filter($this->activeBatches, function ($id) use ($batchId) {
                    return $id !== $batchId;
                });
            }
        }
    }

    private function setMessage($type, $text)
    {
        $this->messageType = $type;
        $this->message = $text;
    }

    private function resetMessages()
    {
        $this->message = '';
        $this->messageType = '';
    }

    public function deleteJob($batchId)
    {
        try {
            // Get all jobs in the batch for the current user
            $jobs = PdfGenerationJob::where('batch_id', $batchId)
                ->where('user_id', auth()->id())
                ->get();

            if ($jobs->isEmpty()) {
                $this->setMessage('error', 'Job not found or you do not have permission to delete it.');

                return;
            }

            // Check authorization for deletion using policy
            $firstJob = $jobs->first();
            if (! auth()->user()->can('delete', $firstJob)) {
                $this->setMessage('error', 'Cannot delete job while it is still processing.');

                return;
            }

            // Delete all jobs in the batch (this will also delete associated files)
            foreach ($jobs as $job) {
                if (auth()->user()->can('delete', $job)) {
                    $job->delete();
                }
            }

            $this->setMessage('success', 'Job and associated files deleted successfully.');
            $this->loadJobs();

        } catch (\Exception $e) {
            $this->setMessage('error', 'Failed to delete job: '.$e->getMessage());
        }
    }

    public function render()
    {
        return view('livewire.csv-to-pdf-processor')
            ->layout('components.layouts.app');
    }
}
