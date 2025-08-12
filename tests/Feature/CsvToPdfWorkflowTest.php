<?php

use App\Jobs\ProcessCsvToPdf;
use App\Livewire\CsvToPdfProcessor;
use App\Models\PdfGenerationJob;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    $this->actingAs(User::factory()->create());
    Storage::fake('local');
});

it('completes full csv to pdf workflow successfully', function () {
    Queue::fake();

    $csvContent = "Number,Customer,Amount\n".
                  "CN-001,John Doe,100.00\n".
                  "CN-002,Jane Smith,200.00\n".
                  'CN-003,Bob Johnson,150.00';

    $file = UploadedFile::fake()->createWithContent('credit_notes.csv', $csvContent);

    // Step 1: Upload CSV through Livewire component
    $component = Livewire::test(CsvToPdfProcessor::class)
        ->set('csvFile', $file)
        ->call('uploadCsv')
        ->assertHasNoErrors()
        ->assertSet('showMapping', true);

    // Step 2: Confirm mapping and process
    $component->call('confirmMapping')
        ->assertSet('toasts', function ($toasts) {
            return count($toasts) > 0 && 
                   $toasts[0]['type'] === 'success' && 
                   str_contains($toasts[0]['message'], 'Processing 3 records');
        });

    // Verify jobs were created - one per CSV row
    expect(PdfGenerationJob::count())->toBe(3);

    $jobs = PdfGenerationJob::all();
    expect($jobs->first()->original_filename)->toBe('credit_notes.csv');

    // Verify queue jobs were dispatched - one for each row
    Queue::assertPushed(ProcessCsvToPdf::class, 3);
});

it('handles empty csv file workflow', function () {
    $file = UploadedFile::fake()->createWithContent('empty.csv', '');

    Livewire::test(CsvToPdfProcessor::class)
        ->set('csvFile', $file)
        ->call('uploadCsv')
        ->assertSet('toasts', function ($toasts) {
            return count($toasts) > 0 && 
                   $toasts[0]['type'] === 'error' && 
                   str_contains($toasts[0]['message'], 'empty');
        });

    // No job should be created for empty CSV
    expect(PdfGenerationJob::count())->toBe(0);
});

it('handles csv with only headers workflow', function () {
    $csvContent = 'Number,Customer,Amount';
    $file = UploadedFile::fake()->createWithContent('headers_only.csv', $csvContent);

    Livewire::test(CsvToPdfProcessor::class)
        ->set('csvFile', $file)
        ->call('uploadCsv')
        ->assertSet('toasts', function ($toasts) {
            return count($toasts) > 0 && 
                   $toasts[0]['type'] === 'error' && 
                   str_contains($toasts[0]['message'], 'empty');
        });

    expect(PdfGenerationJob::count())->toBe(0);
});

it('refreshes job list after successful upload', function () {
    Queue::fake();

    // Create some existing jobs
    PdfGenerationJob::factory(2)->create();

    $csvContent = "Number,Customer,Amount\nCN-001,John Doe,100.00";
    $file = UploadedFile::fake()->createWithContent('refresh_test.csv', $csvContent);

    $component = Livewire::test(CsvToPdfProcessor::class);

    // Upload new CSV and confirm mapping
    $component->set('csvFile', $file)
        ->call('uploadCsv')
        ->assertHasNoErrors()
        ->call('confirmMapping');

    // Verify new job was created (2 existing + 1 new CSV row)
    expect(PdfGenerationJob::count())->toBe(3);
});

it('maintains authentication throughout workflow', function () {
    // Test that unauthenticated users can now access the component (public access)
    auth()->logout();

    $this->get('/generate')
        ->assertStatus(200);

    // Re-authenticate
    $this->actingAs(User::factory()->create());

    // Now should be able to access
    $this->get('/generate')
        ->assertOk();
});

it('handles large csv files within limits', function () {
    Queue::fake();

    // Create CSV with many rows (but under size limit)
    $csvContent = "Number,Customer,Amount\n";
    for ($i = 1; $i <= 100; $i++) {
        $csvContent .= "CN-{$i},Customer {$i},100.00\n";
    }

    $file = UploadedFile::fake()->createWithContent('large_file.csv', $csvContent);

    $component = Livewire::test(CsvToPdfProcessor::class)
        ->set('csvFile', $file)
        ->call('uploadCsv')
        ->assertHasNoErrors()
        ->call('confirmMapping');

    // Should create 100 individual jobs, one per row
    expect(PdfGenerationJob::count())->toBe(100);

    Queue::assertPushed(ProcessCsvToPdf::class, 100);
});
