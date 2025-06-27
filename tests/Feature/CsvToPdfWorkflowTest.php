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
    
    $csvContent = "Number,Customer,Amount\n" .
                  "CN-001,John Doe,100.00\n" .
                  "CN-002,Jane Smith,200.00\n" .
                  "CN-003,Bob Johnson,150.00";
    
    $file = UploadedFile::fake()->createWithContent('credit_notes.csv', $csvContent);
    
    // Step 1: Upload CSV through Livewire component
    Livewire::test(CsvToPdfProcessor::class)
        ->set('csvFile', $file)
        ->call('processCsv')
        ->assertHasNoErrors()
        ->assertSessionHas('success');
    
    // Verify job was created
    expect(PdfGenerationJob::count())->toBe(1);
    
    $job = PdfGenerationJob::first();
    expect($job->original_filename)->toBe('credit_notes.csv')
        ->and($job->total_rows)->toBe(3)
        ->and($job->status)->toBe('pending');
    
    // Verify queue job was dispatched
    Queue::assertPushed(ProcessCsvToPdf::class, function ($job) {
        return count($job->csvData) === 3;
    });
    
    // Step 2: Process the job manually (simulating queue processing)
    $csvData = [
        ['Number' => 'CN-001', 'Customer' => 'John Doe', 'Amount' => '100.00'],
        ['Number' => 'CN-002', 'Customer' => 'Jane Smith', 'Amount' => '200.00'],
        ['Number' => 'CN-003', 'Customer' => 'Bob Johnson', 'Amount' => '150.00']
    ];
    
    $csvPath = storage_path('app/temp-test.csv');
    file_put_contents($csvPath, $csvContent);
    
    $processingJob = new ProcessCsvToPdf($job->batch_id, $csvData, $csvPath);
    $processingJob->handle();
    
    // Step 3: Verify job completion
    $job->refresh();
    
    expect($job->status)->toBe('completed')
        ->and($job->processed_rows)->toBe(3)
        ->and($job->failed_rows)->toBe(0)
        ->and($job->zip_path)->not->toBeNull()
        ->and($job->progress_percentage)->toBe(100.0);
    
    // Verify ZIP file was created
    expect(Storage::exists($job->zip_path))->toBeTrue();
    
    // Step 4: Test download functionality
    $component = Livewire::test(CsvToPdfProcessor::class);
    $response = $component->call('downloadZip', $job->id);
    
    expect($response)->toBeInstanceOf(\Symfony\Component\HttpFoundation\BinaryFileResponse::class);
});

it('handles empty csv file workflow', function () {
    $file = UploadedFile::fake()->createWithContent('empty.csv', '');
    
    Livewire::test(CsvToPdfProcessor::class)
        ->set('csvFile', $file)
        ->call('processCsv')
        ->assertSessionHas('error');
    
    // No job should be created for empty CSV
    expect(PdfGenerationJob::count())->toBe(0);
});

it('handles csv with only headers workflow', function () {
    $csvContent = "Number,Customer,Amount";
    $file = UploadedFile::fake()->createWithContent('headers_only.csv', $csvContent);
    
    Livewire::test(CsvToPdfProcessor::class)
        ->set('csvFile', $file)
        ->call('processCsv')
        ->assertSessionHas('error');
    
    expect(PdfGenerationJob::count())->toBe(0);
});

it('refreshes job list after successful upload', function () {
    Queue::fake();
    
    // Create some existing jobs
    PdfGenerationJob::factory(2)->create();
    
    $csvContent = "Number,Customer,Amount\nCN-001,John Doe,100.00";
    $file = UploadedFile::fake()->createWithContent('refresh_test.csv', $csvContent);
    
    $component = Livewire::test(CsvToPdfProcessor::class);
    
    // Verify initial job count
    expect(count($component->get('jobs')))->toBe(2);
    
    // Upload new CSV
    $component->set('csvFile', $file)
        ->call('processCsv')
        ->assertHasNoErrors();
    
    // Verify job list was refreshed and includes new job
    expect(count($component->get('jobs')))->toBe(3);
});

it('maintains authentication throughout workflow', function () {
    // Test that unauthenticated users cannot access the component
    auth()->logout();
    
    $this->get('/generate')
        ->assertRedirect('/login');
    
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
    
    Livewire::test(CsvToPdfProcessor::class)
        ->set('csvFile', $file)
        ->call('processCsv')
        ->assertHasNoErrors();
    
    $job = PdfGenerationJob::first();
    expect($job->total_rows)->toBe(100);
    
    Queue::assertPushed(ProcessCsvToPdf::class, function ($job) {
        return count($job->csvData) === 100;
    });
});