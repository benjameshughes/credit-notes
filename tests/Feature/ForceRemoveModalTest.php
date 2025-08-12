<?php

use App\Livewire\CsvToPdfProcessor;
use App\Models\PdfGenerationJob;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->user = User::factory()->create();
});

it('shows force remove modal when confirmForceRemove is called', function () {
    $job = PdfGenerationJob::factory()->create([
        'user_id' => null, // Public access
        'status' => 'processing',
        'batch_id' => 'test-batch-123'
    ]);

    Livewire::test(CsvToPdfProcessor::class)
        ->call('confirmForceRemove', $job->batch_id)
        ->assertSet('showForceRemoveModal', true)
        ->assertSet('jobToRemove', $job->batch_id);
});

it('hides modal when cancelForceRemove is called', function () {
    $job = PdfGenerationJob::factory()->create([
        'user_id' => null,
        'status' => 'processing',
        'batch_id' => 'test-batch-456'
    ]);

    Livewire::test(CsvToPdfProcessor::class)
        ->call('confirmForceRemove', $job->batch_id)
        ->assertSet('showForceRemoveModal', true)
        ->call('cancelForceRemove')
        ->assertSet('showForceRemoveModal', false)
        ->assertSet('jobToRemove', null);
});

it('executes force remove and hides modal', function () {
    $job = PdfGenerationJob::factory()->create([
        'user_id' => null,
        'status' => 'processing',
        'batch_id' => 'test-batch-789'
    ]);

    Livewire::test(CsvToPdfProcessor::class)
        ->call('confirmForceRemove', $job->batch_id)
        ->assertSet('showForceRemoveModal', true)
        ->call('executeForceRemove')
        ->assertSet('showForceRemoveModal', false)
        ->assertSet('jobToRemove', null)
        ->assertSet('toasts', function ($toasts) {
            return count($toasts) > 0 && $toasts[0]['type'] === 'success';
        });

    // Verify job was actually deleted
    expect(PdfGenerationJob::find($job->id))->toBeNull();
});

it('renders modal in the blade template', function () {
    $job = PdfGenerationJob::factory()->create([
        'user_id' => null,
        'status' => 'processing',
        'batch_id' => 'test-batch-render'
    ]);

    Livewire::test(CsvToPdfProcessor::class)
        ->call('confirmForceRemove', $job->batch_id)
        ->assertSee('Force Remove Job')
        ->assertSee('This action cannot be undone')
        ->assertSee('Force Remove Job', false) // Text on button
        ->assertSee('Cancel', false); // Text on cancel button
});