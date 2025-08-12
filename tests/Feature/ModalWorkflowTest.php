<?php

use App\Livewire\CsvToPdfProcessor;
use App\Models\PdfGenerationJob;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Livewire\Livewire;

beforeEach(function () {
    $this->user = User::factory()->create();
});

it('can complete full workflow with modal confirmation', function () {
    // Create a job that needs force removal
    $job = PdfGenerationJob::factory()->create([
        'user_id' => null,
        'status' => 'processing',
        'batch_id' => 'stuck-job-123',
        'original_filename' => 'stuck-file.csv',
        'total_rows' => 5,
        'processed_rows' => 2,
        'failed_rows' => 0
    ]);

    $component = Livewire::test(CsvToPdfProcessor::class);

    // Initial state - modal should be hidden
    $component
        ->assertSet('showForceRemoveModal', false)
        ->assertSet('jobToRemove', null);

    // User clicks "Force Remove" button
    $component->call('confirmForceRemove', $job->batch_id);

    // Modal should now be visible with correct batch ID
    $component
        ->assertSet('showForceRemoveModal', true)
        ->assertSet('jobToRemove', $job->batch_id)
        ->assertSee('Force Remove Job')
        ->assertSee('This action cannot be undone');

    // User clicks "Cancel" - should hide modal
    $component->call('cancelForceRemove');
    
    $component
        ->assertSet('showForceRemoveModal', false)
        ->assertSet('jobToRemove', null);

    // Job should still exist
    expect(PdfGenerationJob::find($job->id))->not->toBeNull();

    // User opens modal again and confirms removal
    $component->call('confirmForceRemove', $job->batch_id);
    $component
        ->assertSet('showForceRemoveModal', true)
        ->call('executeForceRemove')
        ->assertSet('showForceRemoveModal', false)
        ->assertSet('jobToRemove', null);

    // Job should be deleted and success toast shown
    expect(PdfGenerationJob::find($job->id))->toBeNull();
    
    $component->assertSet('toasts', function ($toasts) {
        return count($toasts) > 0 && 
               $toasts[0]['type'] === 'success' && 
               str_contains($toasts[0]['message'], 'forcefully removed');
    });
});

it('handles multiple jobs with different batch IDs correctly', function () {
    $job1 = PdfGenerationJob::factory()->create([
        'batch_id' => 'batch-001',
        'status' => 'processing'
    ]);
    
    $job2 = PdfGenerationJob::factory()->create([
        'batch_id' => 'batch-002', 
        'status' => 'packaging'
    ]);

    $component = Livewire::test(CsvToPdfProcessor::class);

    // Open modal for first job
    $component->call('confirmForceRemove', $job1->batch_id);
    $component->assertSet('jobToRemove', $job1->batch_id);

    // Switch to second job without confirming first
    $component->call('confirmForceRemove', $job2->batch_id);
    $component->assertSet('jobToRemove', $job2->batch_id); // Should update to new job

    // Execute removal - should remove job2
    $component->call('executeForceRemove');

    // job1 should still exist, job2 should be deleted
    expect(PdfGenerationJob::find($job1->id))->not->toBeNull();
    expect(PdfGenerationJob::find($job2->id))->toBeNull();
});

it('shows different force remove buttons for different job statuses', function () {
    $pendingJob = PdfGenerationJob::factory()->create([
        'batch_id' => 'pending-job',
        'status' => 'pending'
    ]);
    
    $packagingJob = PdfGenerationJob::factory()->create([
        'batch_id' => 'packaging-job',
        'status' => 'packaging'
    ]);

    $failedJob = PdfGenerationJob::factory()->create([
        'batch_id' => 'failed-job',
        'status' => 'failed'
    ]);

    $component = Livewire::test(CsvToPdfProcessor::class);

    // Should see all job batch IDs displayed in the component
    $component
        ->assertSee('pending-job')
        ->assertSee('packaging-job')
        ->assertSee('failed-job')
        ->assertSee('Force Remove'); // Should see the force remove buttons
});