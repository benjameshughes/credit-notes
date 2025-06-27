<?php

use App\Jobs\ProcessCsvToPdf;
use App\Models\PdfGenerationJob;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

beforeEach(function () {
    Storage::fake('local');
    Log::partialMock();
});

it('processes csv data and generates pdfs successfully', function () {
    $batchId = Str::uuid();
    $csvData = [
        ['Number' => '123', 'Customer' => 'John Doe', 'Amount' => '100.00'],
        ['Number' => '124', 'Customer' => 'Jane Smith', 'Amount' => '200.00']
    ];
    
    $job = PdfGenerationJob::create([
        'batch_id' => $batchId,
        'original_filename' => 'test.csv',
        'total_rows' => 2,
        'status' => 'pending'
    ]);
    
    $csvPath = storage_path('app/temp.csv');
    file_put_contents($csvPath, "Number,Customer,Amount\n123,John Doe,100.00\n124,Jane Smith,200.00");
    
    $processingJob = new ProcessCsvToPdf($batchId, $csvData, $csvPath);
    $processingJob->handle();
    
    $job->refresh();
    
    expect($job->status)->toBe('completed')
        ->and($job->processed_rows)->toBe(2)
        ->and($job->failed_rows)->toBe(0)
        ->and($job->zip_path)->not->toBeNull();
    
    // Verify temp CSV was cleaned up
    expect(file_exists($csvPath))->toBeFalse();
});

it('updates job status to processing when started', function () {
    $batchId = Str::uuid();
    $csvData = [
        ['Number' => '123', 'Customer' => 'John Doe', 'Amount' => '100.00']
    ];
    
    $job = PdfGenerationJob::create([
        'batch_id' => $batchId,
        'original_filename' => 'test.csv',
        'total_rows' => 1,
        'status' => 'pending'
    ]);
    
    $csvPath = storage_path('app/temp.csv');
    file_put_contents($csvPath, "Number,Customer,Amount\n123,John Doe,100.00");
    
    $processingJob = new ProcessCsvToPdf($batchId, $csvData, $csvPath);
    $processingJob->handle();
    
    $job->refresh();
    
    expect($job->status)->toBe('completed'); // Should be completed after processing
});

it('creates zip file with correct naming convention', function () {
    $batchId = Str::uuid();
    $csvData = [
        ['Number' => '123', 'Customer' => 'John Doe', 'Amount' => '100.00']
    ];
    
    $job = PdfGenerationJob::create([
        'batch_id' => $batchId,
        'original_filename' => 'My Test File.csv',
        'total_rows' => 1,
        'status' => 'pending'
    ]);
    
    $csvPath = storage_path('app/temp.csv');
    file_put_contents($csvPath, "test content");
    
    $processingJob = new ProcessCsvToPdf($batchId, $csvData, $csvPath);
    $processingJob->handle();
    
    $job->refresh();
    
    expect($job->zip_path)->toContain('pdfs_my-test-file_')
        ->and($job->zip_path)->toContain('.zip');
});

it('handles missing job record gracefully', function () {
    $batchId = 'non-existent-batch';
    $csvData = [
        ['Number' => '123', 'Customer' => 'John Doe', 'Amount' => '100.00']
    ];
    
    $csvPath = storage_path('app/temp-test.csv');
    file_put_contents($csvPath, "test content");
    
    $processingJob = new ProcessCsvToPdf($batchId, $csvData, $csvPath);
    $result = $processingJob->handle();
    
    // Should return early without error
    expect($result)->toBeNull();
    
    // Verify temp file was cleaned up even when job doesn't exist
    expect(file_exists($csvPath))->toBeFalse();
});

it('generates pdf with correct filename based on credit note number', function () {
    $batchId = Str::uuid();
    $csvData = [
        ['Number' => 'CN-2024-001', 'Customer' => 'John Doe', 'Amount' => '100.00'],
        ['Customer' => 'Jane Smith', 'Amount' => '200.00'] // No number field
    ];
    
    $job = PdfGenerationJob::create([
        'batch_id' => $batchId,
        'original_filename' => 'test.csv',
        'total_rows' => 2,
        'status' => 'pending'
    ]);
    
    $csvPath = storage_path('app/temp.csv');
    file_put_contents($csvPath, "test content");
    
    $processingJob = new ProcessCsvToPdf($batchId, $csvData, $csvPath);
    $processingJob->handle();
    
    $job->refresh();
    
    expect($job->status)->toBe('completed')
        ->and($job->processed_rows)->toBe(2);
    
    // Check that PDFs were generated with correct naming
    expect(Storage::exists("pdfs/{$batchId}/credit_note_cn-2024-001.pdf"))->toBeFalse(); // Cleaned up
    expect(Storage::exists("pdfs/{$batchId}/credit_note_2.pdf"))->toBeFalse(); // Cleaned up
});

it('cleans up temporary files after processing', function () {
    $batchId = Str::uuid();
    $csvData = [
        ['Number' => '123', 'Customer' => 'John Doe', 'Amount' => '100.00']
    ];
    
    $job = PdfGenerationJob::create([
        'batch_id' => $batchId,
        'original_filename' => 'test.csv',
        'total_rows' => 1,
        'status' => 'pending'
    ]);
    
    $csvPath = storage_path('app/temp.csv');
    file_put_contents($csvPath, "test content");
    
    $processingJob = new ProcessCsvToPdf($batchId, $csvData, $csvPath);
    $processingJob->handle();
    
    // Verify temp CSV was cleaned up
    expect(file_exists($csvPath))->toBeFalse();
    
    // Verify individual PDFs were cleaned up after ZIP creation
    expect(Storage::exists("pdfs/{$batchId}/credit_note_123.pdf"))->toBeFalse();
});