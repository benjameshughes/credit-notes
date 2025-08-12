<?php

namespace App\Console\Commands;

use App\Models\PdfGenerationJob;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class CleanupOldFiles extends Command
{
    protected $signature = 'files:cleanup-old {--days=30 : Number of days to keep files}';

    protected $description = 'Clean up old PDF and ZIP files from completed jobs';

    public function handle()
    {
        $days = (int) $this->option('days');
        $cutoffDate = now()->subDays($days);

        $this->info("Cleaning up files older than {$days} days (before {$cutoffDate->format('Y-m-d H:i:s')})...");

        // Find old completed jobs
        $oldJobs = PdfGenerationJob::whereIn('status', ['completed', 'failed', 'completed_with_errors'])
            ->where('created_at', '<', $cutoffDate)
            ->get();

        if ($oldJobs->isEmpty()) {
            $this->info('No old files to clean up.');

            return 0;
        }

        $deletedJobs = 0;
        $deletedFiles = 0;
        $errors = 0;

        foreach ($oldJobs as $job) {
            try {
                // Count files before deletion
                $fileCount = 0;

                if ($job->file_paths && is_array($job->file_paths)) {
                    $fileCount += count($job->file_paths);
                }
                if ($job->zip_path) {
                    $fileCount++;
                }
                if ($job->single_pdf_path) {
                    $fileCount++;
                }

                // Delete the job (this will also delete associated files via the model)
                $job->delete();

                $deletedJobs++;
                $deletedFiles += $fileCount;

                $this->line("Deleted job {$job->id} and {$fileCount} associated files");

            } catch (\Exception $e) {
                $errors++;
                $this->error("Failed to delete job {$job->id}: ".$e->getMessage());
                Log::error("File cleanup error for job {$job->id}: ".$e->getMessage());
            }
        }

        $this->info("\nCleanup completed:");
        $this->info("- Deleted {$deletedJobs} jobs");
        $this->info("- Deleted {$deletedFiles} files");

        if ($errors > 0) {
            $this->warn("- {$errors} errors occurred");
        }

        return 0;
    }
}
