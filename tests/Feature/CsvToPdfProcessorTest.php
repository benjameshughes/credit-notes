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

    Livewire::test(CsvToPdfProcessor::class)
        ->set('csvFile', $file)
        ->call('uploadCsv')
        ->assertHasNoErrors()
        ->assertSet('csvFile', null)
        ->assertSessionHas('success');

    Bus::assertDispatched(ProcessCsvToPdf::class);

    expect(PdfGenerationJob::count())->toBe(1);

    $job = PdfGenerationJob::first();
    expect($job->original_filename)->toBe('test.csv')
        ->and($job->total_rows)->toBe(2)
        ->and($job->status)->toBe('pending');
});

it('handles empty csv files', function () {
    $file = UploadedFile::fake()->createWithContent('empty.csv', '');

    Livewire::test(CsvToPdfProcessor::class)
        ->set('csvFile', $file)
        ->call('uploadCsv')
        ->assertSessionHas('error');
});

it('handles malformed csv files', function () {
    $csvContent = "Number,Customer,Amount\n123,John Doe\n124,Jane Smith,200.00,Extra Column";
    $file = UploadedFile::fake()->createWithContent('malformed.csv', $csvContent);

    Livewire::test(CsvToPdfProcessor::class)
        ->set('csvFile', $file)
        ->call('uploadCsv')
        ->assertHasNoErrors();

    $job = PdfGenerationJob::first();
    expect($job->total_rows)->toBe(1); // Only the properly formatted row
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
        ->assertSet('messageType', 'success');

    expect(PdfGenerationJob::find($job->id))->toBeNull();
});

it('sets uploading state during processing', function () {
    Bus::fake();

    $csvContent = "Number,Customer,Amount\n123,John Doe,100.00";
    $file = UploadedFile::fake()->createWithContent('test.csv', $csvContent);

    $component = Livewire::test(CsvToPdfProcessor::class)
        ->set('csvFile', $file);

    // Check that isUploading is false initially
    expect($component->get('isProcessing'))->toBeFalse();

    $component->call('uploadCsv');

    // After processing, isUploading should be reset to false
    expect($component->get('isProcessing'))->toBeFalse();
});
