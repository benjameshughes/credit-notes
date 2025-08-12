<?php

use App\Jobs\ProcessCsvToPdf;
use App\Livewire\CsvToPdfProcessor;
use App\Models\PdfGenerationJob;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    $this->actingAs(User::factory()->create());
    Storage::fake('local');
});

it('can render the csv to pdf processor component', function () {
    Livewire::test(CsvToPdfProcessor::class)
        ->assertStatus(200);
});

it('loads recent jobs on mount', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    PdfGenerationJob::factory(3)->create(['user_id' => $user->id]);

    Livewire::test(CsvToPdfProcessor::class)
        ->assertSet('processingJobs', function ($jobs) {
            return is_array($jobs);
        })
        ->assertSet('completedJobs', function ($jobs) {
            return is_array($jobs);
        });
});

it('validates csv file upload', function () {
    Livewire::test(CsvToPdfProcessor::class)
        ->set('csvFile', null)
        ->call('uploadCsv')
        ->assertHasErrors(['csvFile' => 'required']);
});

it('validates file type is csv', function () {
    $file = UploadedFile::fake()->create('test.txt', 100, 'text/plain');

    Livewire::test(CsvToPdfProcessor::class)
        ->set('csvFile', $file)
        ->call('uploadCsv')
        ->assertHasErrors(['csvFile']);
});

it('validates file size limit', function () {
    $file = UploadedFile::fake()->create('test.csv', 11000); // 11MB > 10MB limit

    Livewire::test(CsvToPdfProcessor::class)
        ->set('csvFile', $file)
        ->call('uploadCsv')
        ->assertHasErrors(['csvFile']);
});

it('processes valid csv file successfully', function () {
    Bus::fake();

    $csvContent = "Number,Customer,Amount\n123,John Doe,100.00\n124,Jane Smith,200.00";
    $file = UploadedFile::fake()->createWithContent('test.csv', $csvContent);

    $component = Livewire::test(CsvToPdfProcessor::class)
        ->set('csvFile', $file)
        ->call('uploadCsv')
        ->assertHasNoErrors()
        ->assertSet('csvFile', null)
        ->assertSet('showMapping', true)
        ->assertSet('csvHeaders', ['Number', 'Customer', 'Amount']);

    // Now confirm the mapping
    $component->call('confirmMapping')
        ->assertSet('toasts', function ($toasts) {
            return count($toasts) > 0 && 
                   $toasts[0]['type'] === 'success' && 
                   str_contains($toasts[0]['message'], 'Processing 2 records');
        });

    Bus::assertDispatched(ProcessCsvToPdf::class);
    expect(PdfGenerationJob::count())->toBe(2); // One job per CSV row
});

it('handles empty csv files', function () {
    $file = UploadedFile::fake()->createWithContent('empty.csv', '');

    Livewire::test(CsvToPdfProcessor::class)
        ->set('csvFile', $file)
        ->call('uploadCsv')
        ->assertSet('toasts', function ($toasts) {
            return count($toasts) > 0 && 
                   $toasts[0]['type'] === 'error' && 
                   (str_contains($toasts[0]['message'], 'empty') || str_contains($toasts[0]['message'], 'CSV file is empty'));
        });
});

it('handles malformed csv files', function () {
    $csvContent = "Number,Customer,Amount\n123,John Doe\n124,Jane Smith,200.00,Extra Column";
    $file = UploadedFile::fake()->createWithContent('malformed.csv', $csvContent);

    Livewire::test(CsvToPdfProcessor::class)
        ->set('csvFile', $file)
        ->call('uploadCsv')
        ->assertSet('toasts', function ($toasts) {
            return count($toasts) > 0 && 
                   $toasts[0]['type'] === 'error' && 
                   (str_contains($toasts[0]['message'], 'empty') || str_contains($toasts[0]['message'], 'no valid data'));
        });

    // No jobs should be created as all rows are malformed
    expect(PdfGenerationJob::count())->toBe(0);
});

it('can delete completed jobs', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $job = PdfGenerationJob::factory()->create([
        'user_id' => $user->id,
        'status' => 'completed',
    ]);

    Livewire::test(CsvToPdfProcessor::class)
        ->call('deleteJob', $job->batch_id)
        ->assertSet('toasts', function ($toasts) {
            return count($toasts) > 0 && $toasts[0]['type'] === 'success';
        });

    expect(PdfGenerationJob::find($job->id))->toBeNull();
});

it('sets uploading state during processing', function () {
    Bus::fake();

    $csvContent = "Number,Customer,Amount\n123,John Doe,100.00";
    $file = UploadedFile::fake()->createWithContent('test.csv', $csvContent);

    $component = Livewire::test(CsvToPdfProcessor::class)
        ->set('csvFile', $file);

    // Check that isProcessing is false initially
    expect($component->get('isProcessing'))->toBeFalse();

    $component->call('uploadCsv');

    // After upload, isProcessing should be reset to false but showMapping should be true
    expect($component->get('isProcessing'))->toBeFalse();
    expect($component->get('showMapping'))->toBeTrue();
});
