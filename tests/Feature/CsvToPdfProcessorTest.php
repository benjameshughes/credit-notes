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
    PdfGenerationJob::factory(3)->create();

    Livewire::test(CsvToPdfProcessor::class)
        ->assertSet('jobs', function ($jobs) {
            return count($jobs) === 3;
        });
});

it('validates csv file upload', function () {
    Livewire::test(CsvToPdfProcessor::class)
        ->set('csvFile', null)
        ->call('processCsv')
        ->assertHasErrors(['csvFile' => 'required']);
});

it('validates file type is csv', function () {
    $file = UploadedFile::fake()->create('test.txt', 100, 'text/plain');

    Livewire::test(CsvToPdfProcessor::class)
        ->set('csvFile', $file)
        ->call('updatedCsvFile')
        ->assertHasErrors(['csvFile']);
});

it('validates file size limit', function () {
    $file = UploadedFile::fake()->create('test.csv', 11000); // 11MB > 10MB limit

    Livewire::test(CsvToPdfProcessor::class)
        ->set('csvFile', $file)
        ->call('updatedCsvFile')
        ->assertHasErrors(['csvFile']);
});

it('processes valid csv file successfully', function () {
    Bus::fake();

    $csvContent = "Number,Customer,Amount\n123,John Doe,100.00\n124,Jane Smith,200.00";
    $file = UploadedFile::fake()->createWithContent('test.csv', $csvContent);

    Livewire::test(CsvToPdfProcessor::class)
        ->set('csvFile', $file)
        ->call('processCsv')
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
        ->call('processCsv')
        ->assertSessionHas('error');
});

it('handles malformed csv files', function () {
    $csvContent = "Number,Customer,Amount\n123,John Doe\n124,Jane Smith,200.00,Extra Column";
    $file = UploadedFile::fake()->createWithContent('malformed.csv', $csvContent);

    Livewire::test(CsvToPdfProcessor::class)
        ->set('csvFile', $file)
        ->call('processCsv')
        ->assertHasNoErrors();

    $job = PdfGenerationJob::first();
    expect($job->total_rows)->toBe(1); // Only the properly formatted row
});

it('can download completed job zip file', function () {
    Storage::put('downloads/test.zip', 'fake zip content');

    $job = PdfGenerationJob::create([
        'batch_id' => 'test-batch',
        'original_filename' => 'test.csv',
        'total_rows' => 1,
        'status' => 'completed',
        'zip_path' => 'downloads/test.zip',
    ]);

    $response = Livewire::test(CsvToPdfProcessor::class)
        ->call('downloadZip', $job->id);

    expect($response)->toBeInstanceOf(\Symfony\Component\HttpFoundation\BinaryFileResponse::class);
});

it('handles download request for non-existent job', function () {
    Livewire::test(CsvToPdfProcessor::class)
        ->call('downloadZip', 999)
        ->assertSessionHas('error');
});

it('handles download request for incomplete job', function () {
    $job = PdfGenerationJob::create([
        'batch_id' => 'test-batch',
        'original_filename' => 'test.csv',
        'total_rows' => 1,
        'status' => 'processing',
    ]);

    Livewire::test(CsvToPdfProcessor::class)
        ->call('downloadZip', $job->id)
        ->assertSessionHas('error');
});

it('sets uploading state during processing', function () {
    Bus::fake();

    $csvContent = "Number,Customer,Amount\n123,John Doe,100.00";
    $file = UploadedFile::fake()->createWithContent('test.csv', $csvContent);

    $component = Livewire::test(CsvToPdfProcessor::class)
        ->set('csvFile', $file);

    // Check that isUploading is false initially
    expect($component->get('isUploading'))->toBeFalse();

    $component->call('processCsv');

    // After processing, isUploading should be reset to false
    expect($component->get('isUploading'))->toBeFalse();
});
