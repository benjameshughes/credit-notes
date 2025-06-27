<?php

use App\Models\PdfGenerationJob;

it('can create a pdf generation job', function () {
    $job = PdfGenerationJob::create([
        'batch_id' => 'test-batch-123',
        'original_filename' => 'test.csv',
        'total_rows' => 10,
        'status' => 'pending'
    ]);
    
    expect($job->batch_id)->toBe('test-batch-123')
        ->and($job->original_filename)->toBe('test.csv')
        ->and($job->total_rows)->toBe(10)
        ->and($job->status)->toBe('pending')
        ->and($job->processed_rows)->toBeNull()
        ->and($job->failed_rows)->toBeNull();
});

it('calculates progress percentage correctly', function () {
    $job = PdfGenerationJob::create([
        'batch_id' => 'test-batch',
        'original_filename' => 'test.csv',
        'total_rows' => 100,
        'processed_rows' => 25,
        'status' => 'processing'
    ]);
    
    expect($job->progress_percentage)->toBe(25);
});

it('calculates progress percentage for partial completion', function () {
    $job = PdfGenerationJob::create([
        'batch_id' => 'test-batch',
        'original_filename' => 'test.csv', 
        'total_rows' => 3,
        'processed_rows' => 1,
        'status' => 'processing'
    ]);
    
    expect($job->progress_percentage)->toBe(33.33);
});

it('returns zero progress for zero total rows', function () {
    $job = PdfGenerationJob::create([
        'batch_id' => 'test-batch',
        'original_filename' => 'test.csv',
        'total_rows' => 0,
        'processed_rows' => 0,
        'status' => 'pending'
    ]);
    
    expect($job->progress_percentage)->toBe(0);
});

it('returns zero progress when no rows processed yet', function () {
    $job = PdfGenerationJob::create([
        'batch_id' => 'test-batch',
        'original_filename' => 'test.csv',
        'total_rows' => 10,
        'status' => 'pending'
    ]);
    
    expect($job->progress_percentage)->toBe(0);
});

it('handles complete progress calculation', function () {
    $job = PdfGenerationJob::create([
        'batch_id' => 'test-batch',
        'original_filename' => 'test.csv',
        'total_rows' => 50,
        'processed_rows' => 50,
        'status' => 'completed'
    ]);
    
    expect($job->progress_percentage)->toBe(100);
});

it('casts file_paths as array', function () {
    $filePaths = ['path1.pdf', 'path2.pdf', 'path3.pdf'];
    
    $job = PdfGenerationJob::create([
        'batch_id' => 'test-batch',
        'original_filename' => 'test.csv',
        'total_rows' => 3,
        'status' => 'completed',
        'file_paths' => $filePaths
    ]);
    
    expect($job->file_paths)->toBeArray()
        ->and($job->file_paths)->toBe($filePaths);
});

it('stores null file_paths correctly', function () {
    $job = PdfGenerationJob::create([
        'batch_id' => 'test-batch',
        'original_filename' => 'test.csv',
        'total_rows' => 3,
        'status' => 'pending'
    ]);
    
    expect($job->file_paths)->toBeNull();
});

it('can update job status and progress', function () {
    $job = PdfGenerationJob::create([
        'batch_id' => 'test-batch',
        'original_filename' => 'test.csv',
        'total_rows' => 10,
        'status' => 'pending'
    ]);
    
    $job->update([
        'status' => 'processing',
        'processed_rows' => 5,
        'failed_rows' => 1
    ]);
    
    expect($job->fresh()->status)->toBe('processing')
        ->and($job->fresh()->processed_rows)->toBe(5)
        ->and($job->fresh()->failed_rows)->toBe(1)
        ->and($job->fresh()->progress_percentage)->toBe(50);
});

it('can store zip path', function () {
    $job = PdfGenerationJob::create([
        'batch_id' => 'test-batch',
        'original_filename' => 'test.csv',
        'total_rows' => 5,
        'status' => 'completed',
        'zip_path' => 'downloads/test_batch_2024.zip'
    ]);
    
    expect($job->zip_path)->toBe('downloads/test_batch_2024.zip');
});

it('has correct fillable attributes', function () {
    $job = new PdfGenerationJob();
    
    expect($job->getFillable())->toContain('batch_id')
        ->and($job->getFillable())->toContain('original_filename')
        ->and($job->getFillable())->toContain('total_rows')
        ->and($job->getFillable())->toContain('processed_rows')
        ->and($job->getFillable())->toContain('failed_rows')
        ->and($job->getFillable())->toContain('status')
        ->and($job->getFillable())->toContain('file_paths')
        ->and($job->getFillable())->toContain('zip_path');
});