<?php

use Illuminate\Support\Facades\Storage;
use Spatie\LaravelPdf\Facades\Pdf;

it('can generate a real PDF using Spatie Laravel PDF', function () {
    Storage::fake('local');

    $data = [
        'Reference' => 'CN-2024-001',
        'Number' => '123',
        'Date' => '01/01/2024',
        'Type' => 'Credit Note',
        'Net GBP' => '83.33',
        'VAT GBP' => '16.67',
        'Total GBP' => '100.00',
    ];

    $filename = 'test-credit-note.pdf';
    $path = storage_path('app/'.$filename);

    // Generate the PDF
    Pdf::view('pdf.credit-note-template', compact('data'))
        ->format('a4')
        ->save($path);

    // Verify the file was created
    expect(file_exists($path))->toBeTrue();

    // Verify it's a PDF file (starts with PDF signature)
    $content = file_get_contents($path);
    expect($content)->toStartWith('%PDF');

    // Cleanup
    unlink($path);
})->skip(function () {
    // Skip if Node.js dependencies aren't available for Browsershot
    return ! class_exists('\Spatie\Browsershot\Browsershot');
}, 'Browsershot dependencies not available');

it('verifies that Spatie Laravel PDF is properly installed', function () {
    expect(class_exists('\Spatie\LaravelPdf\Facades\Pdf'))->toBeTrue();
    expect(class_exists('\Spatie\LaravelPdf\PdfBuilder'))->toBeTrue();
});
