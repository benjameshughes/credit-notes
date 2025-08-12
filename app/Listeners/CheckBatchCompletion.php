<?php

namespace App\Listeners;

use App\Events\BatchCompleted;
use App\Events\PdfJobCompleted;
use App\Models\PdfGenerationJob;
use Illuminate\Support\Facades\Log;

class CheckBatchCompletion
{
    public function handle(PdfJobCompleted $event): void
    {
        $completedJob = $event->job;
        $batchId = $completedJob->batch_id;
        
        Log::info("Checking batch completion for {$batchId}");
        
        // Simple check - count remaining jobs
        $totalJobs = PdfGenerationJob::where('batch_id', $batchId)->count();
        $completedJobs = PdfGenerationJob::where('batch_id', $batchId)
            ->where('status', 'completed')
            ->count();
            
        Log::info("Batch {$batchId}: {$completedJobs}/{$totalJobs} jobs completed");
        
        // If all jobs are complete, dispatch the batch completed event
        if ($completedJobs >= $totalJobs) {
            // Check if batch is already processed (has download paths) 
            $alreadyProcessed = PdfGenerationJob::where('batch_id', $batchId)
                ->whereNotNull('zip_path')
                ->exists();
                
            if (!$alreadyProcessed) {
                Log::info("Batch {$batchId} is complete - dispatching BatchCompleted event");
                BatchCompleted::dispatch($batchId, $completedJob->user_id);
            } else {
                Log::info("Batch {$batchId} already processed");
            }
        }
    }
}