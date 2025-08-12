<?php

use App\Livewire\CsvToPdfProcessor;
use App\Livewire\Settings\Appearance;
use App\Livewire\Settings\Password;
use App\Livewire\Settings\Profile;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route('generate');
})->name('home');

Route::view('dashboard', 'dashboard')
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

Route::get('generate', CsvToPdfProcessor::class)
    ->name('generate');

Route::get('download/{batchId}', function ($batchId) {
    $job = \App\Models\PdfGenerationJob::where('batch_id', $batchId)
        ->where(function ($query) {
            $query->whereNotNull('zip_path')->orWhereNotNull('single_pdf_path');
        })
        ->first();

    if (! $job) {
        abort(404, 'Download not available');
    }

    // Determine if it's a ZIP or single PDF
    if ($job->zip_path) {
        // Multiple PDFs - serve ZIP file
        $filePath = storage_path('app/'.$job->zip_path);
        $filename = basename($job->zip_path);
        $contentType = 'application/zip';
    } elseif ($job->single_pdf_path) {
        // Single PDF - serve individual PDF
        $filePath = storage_path('app/'.$job->single_pdf_path);
        $filename = basename($job->single_pdf_path);
        $contentType = 'application/pdf';
    } else {
        abort(404, 'No download available');
    }

    if (! file_exists($filePath)) {
        abort(404, 'File not found');
    }

    $headers = [
        'Content-Type' => $contentType,
        'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        'Content-Length' => filesize($filePath),
        'Cache-Control' => 'no-cache, no-store, must-revalidate',
        'Pragma' => 'no-cache',
        'Expires' => '0',
    ];

    return response()->stream(function () use ($filePath) {
        $stream = fopen($filePath, 'rb');

        if ($stream === false) {
            abort(404, 'File not found');
        }

        while (! feof($stream)) {
            echo fread($stream, 8192); // Read in 8KB chunks
            flush(); // Force output to browser

            // Check if client disconnected
            if (connection_aborted()) {
                break;
            }
        }

        fclose($stream);
    }, 200, $headers);
})->name('download');

Route::middleware(['auth'])->group(function () {
    Route::redirect('settings', 'settings/profile');

    Route::get('settings/profile', Profile::class)->name('settings.profile');
    Route::get('settings/password', Password::class)->name('settings.password');
    Route::get('settings/appearance', Appearance::class)->name('settings.appearance');
});

require __DIR__.'/auth.php';
