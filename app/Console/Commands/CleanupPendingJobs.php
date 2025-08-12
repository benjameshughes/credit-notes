<?php

namespace App\Console\Commands;

use App\Models\PdfGenerationJob;
use Illuminate\Console\Command;

class CleanupPendingJobs extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'jobs:cleanup-pending 
                            {--hours=0.5 : Delete pending jobs older than this many hours}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Delete pending jobs that have been stuck for too long';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $hours = $this->option('hours');
        $cutoffTime = now()->subHours($hours);

        $this->info("Cleaning up pending jobs older than {$hours} hours...");

        // Find old pending jobs
        $oldPendingJobs = PdfGenerationJob::where('status', 'pending')
            ->where('created_at', '<', $cutoffTime)
            ->get();

        if ($oldPendingJobs->isEmpty()) {
            $this->info('No old pending jobs found.');

            return Command::SUCCESS;
        }

        $this->info("Found {$oldPendingJobs->count()} old pending jobs.");

        $deletedCount = 0;
        $failedCount = 0;

        foreach ($oldPendingJobs as $job) {
            try {
                // This will also clean up any associated files
                $job->delete();
                $deletedCount++;
            } catch (\Exception $e) {
                $failedCount++;
                $this->error("Failed to delete job ID {$job->id}: ".$e->getMessage());
            }
        }

        $this->info("Successfully deleted {$deletedCount} jobs.");

        if ($failedCount > 0) {
            $this->warn("Failed to delete {$failedCount} jobs.");
        }

        return Command::SUCCESS;
    }
}
