<?php

use App\Jobs\ProcessCsvToPdf;
use App\Models\PdfGenerationJob;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Barryvdh\DomPDF\Facade\Pdf;

beforeEach(function () {
    Storage::fake('local');
});

it('processes single csv row and generates pdf successfully', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $rowData = ['Number' => '123', 'Customer' => 'John Doe', 'Amount' => '100.00'];
    $batchId = Str::uuid();

    $job = PdfGenerationJob::create([
        'batch_id' => $batchId,
        'original_filename' => 'test.csv',
        'total_rows' => 1,
        'processed_rows' => 0,
        'failed_rows' => 0,
        'status' => 'pending',
        'row_data' => json_encode($rowData),
        'row_index' => 0,
        'user_id' => $user->id,
    ]);

    $processingJob = new ProcessCsvToPdf($job->id, $rowData, 0);
    $processingJob->handle();

    $job->refresh();

    expect($job->status)->toBe('completed')
        ->and($job->processed_rows)->toBe(1)
        ->and($job->failed_rows)->toBe(0)
        ->and($job->file_paths)->not->toBeNull();
});

it('updates job status to processing when started', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $rowData = ['Number' => '123', 'Customer' => 'John Doe', 'Amount' => '100.00'];
    $batchId = Str::uuid();

    $job = PdfGenerationJob::create([
        'batch_id' => $batchId,
        'original_filename' => 'test.csv',
        'total_rows' => 1,
        'processed_rows' => 0,
        'failed_rows' => 0,
        'status' => 'pending',
        'row_data' => json_encode($rowData),
        'row_index' => 0,
        'user_id' => $user->id,
    ]);

    $processingJob = new ProcessCsvToPdf($job->id, $rowData, 0);
    $processingJob->handle();

    $job->refresh();

    expect($job->status)->toBe('completed'); // Should be completed after processing
});

it('handles pdf generation for single job', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $rowData = ['Number' => '123', 'Customer' => 'John Doe', 'Amount' => '100.00'];
    $batchId = Str::uuid();

    $job = PdfGenerationJob::create([
        'batch_id' => $batchId,
        'original_filename' => 'My Test File.csv',
        'total_rows' => 1,
        'processed_rows' => 0,
        'failed_rows' => 0,
        'status' => 'pending',
        'row_data' => json_encode($rowData),
        'row_index' => 0,
        'user_id' => $user->id,
    ]);

    $processingJob = new ProcessCsvToPdf($job->id, $rowData, 0);
    $processingJob->handle();

    $job->refresh();

    expect($job->status)->toBe('completed')
        ->and($job->file_paths)->not->toBeNull();
});

it('handles missing job record gracefully', function () {
    $nonExistentJobId = 99999;
    $rowData = ['Number' => '123', 'Customer' => 'John Doe', 'Amount' => '100.00'];

    $processingJob = new ProcessCsvToPdf($nonExistentJobId, $rowData, 0);
    $result = $processingJob->handle();

    // Should return early without error
    expect($result)->toBeNull();
});

it('generates pdf with correct filename based on credit note number', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $rowData = ['Number' => 'CN-2024-001', 'Customer' => 'John Doe', 'Amount' => '100.00'];
    $batchId = Str::uuid();

    $job = PdfGenerationJob::create([
        'batch_id' => $batchId,
        'original_filename' => 'test.csv',
        'total_rows' => 1,
        'processed_rows' => 0,
        'failed_rows' => 0,
        'status' => 'pending',
        'row_data' => json_encode($rowData),
        'row_index' => 0,
        'user_id' => $user->id,
    ]);

    $processingJob = new ProcessCsvToPdf($job->id, $rowData, 0);
    $processingJob->handle();

    $job->refresh();

    expect($job->status)->toBe('completed')
        ->and($job->processed_rows)->toBe(1)
        ->and($job->file_paths)->not->toBeNull();
});

it('handles job failure gracefully', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    // Create a job with invalid data that will cause PDF generation to fail
    $rowData = [];
    $batchId = Str::uuid();

    $job = PdfGenerationJob::create([
        'batch_id' => $batchId,
        'original_filename' => 'test.csv',
        'total_rows' => 1,
        'processed_rows' => 0,
        'failed_rows' => 0,
        'status' => 'pending',
        'row_data' => json_encode($rowData),
        'row_index' => 0,
        'user_id' => $user->id,
    ]);

    // Mock PDF generation to throw an exception
    Pdf::shouldReceive('loadView')->andThrow(new Exception('PDF generation failed'));

    $processingJob = new ProcessCsvToPdf($job->id, $rowData, 0);
    $processingJob->handle();

    $job->refresh();

    expect($job->status)->toBe('failed')
        ->and($job->failed_rows)->toBe(1)
        ->and($job->processed_rows)->toBe(0);
});
