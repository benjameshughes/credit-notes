<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Str;
use App\Models\PdfGenerationJob;
use Illuminate\Support\Facades\Log;

$batchId = '17443b11-d593-4fc5-b26c-ff0827841439';

echo "Checking batch $batchId...\n";

$jobs = PdfGenerationJob::where('batch_id', $batchId)->get();
$completedJobs = $jobs->where('status', 'completed');
$failedJobs = $jobs->where('status', 'failed');
$processingJobs = $jobs->whereIn('status', ['processing', 'pending']);

echo "Total: {$jobs->count()}, Completed: {$completedJobs->count()}, Failed: {$failedJobs->count()}, Processing: {$processingJobs->count()}\n";

// Check if batch is complete
if (($completedJobs->count() + $failedJobs->count()) === $jobs->count() && $processingJobs->count() === 0) {
    // Check if we've already processed this batch
    $alreadyProcessed = $jobs->whereNotNull('zip_path')->count() > 0 || $jobs->whereNotNull('single_pdf_path')->count() > 0;
    
    echo "Batch is complete. Already processed: " . ($alreadyProcessed ? 'yes' : 'no') . "\n";

    if (!$alreadyProcessed) {
        $pdfPaths = [];
        
        foreach ($completedJobs as $job) {
            $filePaths = json_decode($job->file_paths, true);
            if ($filePaths) {
                $pdfPaths = array_merge($pdfPaths, $filePaths);
            }
        }
        
        echo "Found " . count($pdfPaths) . " PDF files\n";
        
        if (!empty($pdfPaths)) {
            if (count($pdfPaths) > 1) {
                // Create ZIP file
                $originalFilename = $jobs->first()->original_filename;
                $zipFilename = 'pdfs_' . Str::slug(pathinfo($originalFilename, PATHINFO_FILENAME)) . '_' . date('Y-m-d_H-i-s') . '.zip';
                $zipPath = 'downloads/' . $zipFilename;
                $fullZipPath = storage_path('app/' . $zipPath);

                // Ensure directory exists
                $dir = dirname($fullZipPath);
                if (!is_dir($dir)) {
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
                        } else {
                            echo "File not found: $fullPdfPath\n";
                        }
                    }
                    
                    $zip->close();
                    
                    if (file_exists($fullZipPath) && filesize($fullZipPath) > 0) {
                        echo "ZIP file created: $zipPath with $addedFiles files\n";
                        
                        // Update all jobs in batch with the zip path
                        PdfGenerationJob::where('batch_id', $batchId)->update(['zip_path' => $zipPath]);
                        echo "Updated all jobs with ZIP path\n";
                        
                    } else {
                        echo "ZIP file was not created properly\n";
                    }
                } else {
                    echo "Could not create ZIP file, error code: $result\n";
                }
            } else {
                // Single PDF
                $singlePdfPath = $pdfPaths[0];
                PdfGenerationJob::where('batch_id', $batchId)->update(['single_pdf_path' => $singlePdfPath]);
                echo "Set single PDF path: $singlePdfPath\n";
            }
        }
    }
} else {
    echo "Batch not complete yet\n";
}

echo "Done!\n";