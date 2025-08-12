<?php

namespace App\Listeners;

use App\Events\BatchCompleted;
use App\Events\BatchDownloadStarted;
use App\Events\DownloadReady;
use App\Models\PdfGenerationJob;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use ZipArchive;

class CreateBatchDownload
{
    public function handle(BatchCompleted $event): void
    {
        $batchId = $event->batchId;
        $userId = $event->userId;
        
        Log::info("Creating download for batch {$batchId}");
        
        // Dispatch event to notify UI that download creation has started
        BatchDownloadStarted::dispatch($batchId, $userId);
        
        // Update download_status to 'creating_download' 
        PdfGenerationJob::where('batch_id', $batchId)
            ->update(['download_status' => 'creating_download']);
        
        // Check if this batch already has download paths (idempotent check)
        $existingDownload = PdfGenerationJob::where('batch_id', $batchId)
            ->where(function ($query) {
                $query->whereNotNull('zip_path')
                      ->orWhereNotNull('single_pdf_path');
            })
            ->exists();
            
        if ($existingDownload) {
            Log::info("Batch {$batchId} already has download paths - skipping");
            return;
        }
        
        // Get all completed jobs in this batch
        $jobs = PdfGenerationJob::where('batch_id', $batchId)
            ->where('status', 'completed')
            ->get();
            
        if ($jobs->isEmpty()) {
            Log::warning("No completed jobs found for batch {$batchId}");
            return;
        }
        
        // Collect all PDF paths
        $pdfPaths = [];
        foreach ($jobs as $job) {
            $filePaths = json_decode($job->file_paths, true);
            if ($filePaths) {
                $pdfPaths = array_merge($pdfPaths, $filePaths);
            }
        }
        
        if (empty($pdfPaths)) {
            Log::warning("No PDF files found for completed batch {$batchId}");
            return;
        }
        
        $firstJob = $jobs->first();
        
        try {
            $downloadPath = null;
            $downloadType = null;
            
            if (count($pdfPaths) > 1) {
                // Multiple PDFs - create ZIP file
                Log::info("Starting ZIP creation for batch {$batchId} - adding 5 second delay for testing");
                sleep(5); // Artificial delay for testing the packaging UI
                $zipPath = $this->createZipFile($pdfPaths, $firstJob->original_filename);
                
                Log::info("Created ZIP file for batch {$batchId}: {$zipPath}");
                
                // Update all jobs in batch with the zip path and status
                PdfGenerationJob::where('batch_id', $batchId)
                    ->update([
                        'zip_path' => $zipPath,
                        'download_status' => 'ready'
                    ]);
                    
                // Clean up individual PDF files after zipping
                $this->cleanup($pdfPaths);
                
                $downloadPath = $zipPath;
                $downloadType = 'zip';
            } else {
                // Single PDF - keep the individual PDF file
                $singlePdfPath = $pdfPaths[0];
                
                Log::info("Setting single PDF path for batch {$batchId}: {$singlePdfPath}");
                
                // Update all jobs in batch with the single PDF path and status
                PdfGenerationJob::where('batch_id', $batchId)
                    ->update([
                        'single_pdf_path' => $singlePdfPath,
                        'download_status' => 'ready'
                    ]);
                    
                $downloadPath = $singlePdfPath;
                $downloadType = 'single_pdf';
            }
            
            // Dispatch event to notify UI that download is ready
            DownloadReady::dispatch($batchId, $userId, $downloadType, $downloadPath);
            
            Log::info("Download creation completed for batch {$batchId} - {$downloadType}: {$downloadPath}");
            
        } catch (\Exception $e) {
            Log::error("Failed to create download for batch {$batchId}: " . $e->getMessage());
            
            // Mark the batch as failed
            PdfGenerationJob::where('batch_id', $batchId)
                ->where('status', 'completed')
                ->update([
                    'status' => 'download_failed',
                    'download_status' => 'failed'
                ]);
                
            throw $e; // Let Laravel handle the retry if configured
        }
    }
    
    private function createZipFile($pdfPaths, $originalFilename)
    {
        $zipFilename = 'pdfs_'.Str::slug(pathinfo($originalFilename, PATHINFO_FILENAME)).'_'.date('Y-m-d_H-i-s').'.zip';
        $zipPath = 'downloads/'.$zipFilename;
        $fullZipPath = storage_path('app/'.$zipPath);

        // Ensure directory exists
        $dir = dirname($fullZipPath);
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $zip = new ZipArchive;
        $result = $zip->open($fullZipPath, ZipArchive::CREATE);

        if ($result === TRUE) {
            $addedFiles = 0;
            foreach ($pdfPaths as $pdfPath) {
                $fullPdfPath = storage_path('app/' . $pdfPath);
                if (file_exists($fullPdfPath)) {
                    if ($zip->addFile($fullPdfPath, basename($pdfPath))) {
                        $addedFiles++;
                    }
                }
            }

            $zip->close();

            // Verify the ZIP file was actually created and has content
            if (file_exists($fullZipPath) && filesize($fullZipPath) > 0) {
                Log::info("ZIP file created successfully: {$zipPath} with {$addedFiles} files");
                return $zipPath;
            } else {
                Log::error("ZIP file was not created properly: {$zipPath}");
                throw new \Exception('ZIP file was not created properly');
            }
        }

        Log::error("Could not open ZIP file for creation: {$zipPath}, error code: {$result}");
        throw new \Exception("Could not create zip file. Error code: {$result}");
    }

    private function cleanup($pdfPaths)
    {
        foreach ($pdfPaths as $path) {
            Storage::delete($path);
        }
    }
}