<?php

namespace App\Console\Commands;

use App\Models\PdfGenerationJob;
use Illuminate\Console\Command;

class CleanupCompletedJobs extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'jobs:cleanup-completed {--hours=24 : Number of hours to keep completed jobs with ZIP files}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Clean up completed PDF generation jobs with ZIP files older than specified hours';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $hours = (int) $this->option('hours');
        
        $this->info("Cleaning up completed jobs with ZIP files older than {$hours} hours...");
        
        // Find completed jobs with ZIP or PDF files
        $completedJobs = PdfGenerationJob::whereIn('status', [
                'completed', 
                'completed_with_errors', 
                'failed',
                'download_failed'
            ])
            ->where(function ($query) {
                $query->whereNotNull('zip_path')
                      ->orWhereNotNull('single_pdf_path');
            })
            ->get();
            
        if ($completedJobs->isEmpty()) {
            $this->info('No completed jobs with files found.');
            return 0;
        }
        
        $deletedCount = 0;
        $cutoffTime = now()->subHours($hours);
        
        foreach ($completedJobs as $job) {
            try {
                $shouldDelete = false;
                
                // Check ZIP file age if it exists
                if ($job->zip_path) {
                    $zipPath = storage_path('app/' . $job->zip_path);
                    if (file_exists($zipPath)) {
                        $fileModTime = filemtime($zipPath);
                        $fileTime = \Carbon\Carbon::createFromTimestamp($fileModTime);
                        if ($fileTime->lt($cutoffTime)) {
                            $shouldDelete = true;
                            $this->info("ZIP file {$job->zip_path} is older than {$hours} hours");
                        }
                    } else {
                        // ZIP file doesn't exist, safe to delete the job record
                        $shouldDelete = true;
                        $this->info("ZIP file {$job->zip_path} not found, cleaning up job record");
                    }
                }
                
                // Check single PDF file age if it exists
                if (!$shouldDelete && $job->single_pdf_path) {
                    $pdfPath = storage_path('app/' . $job->single_pdf_path);
                    if (file_exists($pdfPath)) {
                        $fileModTime = filemtime($pdfPath);
                        $fileTime = \Carbon\Carbon::createFromTimestamp($fileModTime);
                        if ($fileTime->lt($cutoffTime)) {
                            $shouldDelete = true;
                            $this->info("PDF file {$job->single_pdf_path} is older than {$hours} hours");
                        }
                    } else {
                        // PDF file doesn't exist, safe to delete the job record
                        $shouldDelete = true;
                        $this->info("PDF file {$job->single_pdf_path} not found, cleaning up job record");
                    }
                }
                
                if ($shouldDelete) {
                    // Delete will trigger the model's deleting event to clean up files
                    $job->delete();
                    $deletedCount++;
                }
                
            } catch (\Exception $e) {
                $this->error("Failed to process job {$job->id}: " . $e->getMessage());
            }
        }
        
        if ($deletedCount > 0) {
            $this->info("Successfully deleted {$deletedCount} completed jobs and their associated files.");
        } else {
            $this->info("No jobs found with files older than {$hours} hours.");
        }
        
        return 0;
    }
}
