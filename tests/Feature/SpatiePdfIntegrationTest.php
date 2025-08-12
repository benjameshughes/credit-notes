<?php

use Spatie\LaravelPdf\Facades\Pdf;
use Spatie\LaravelPdf\PdfBuilder;

it('can use Spatie Laravel PDF facade', function () {
    // Mock the PDF facade to test integration
    $mockBuilder = Mockery::mock(PdfBuilder::class);
    $mockBuilder->shouldReceive('format')->with('a4')->once()->andReturnSelf();
    $mockBuilder->shouldReceive('save')->with(Mockery::type('string'))->once()->andReturnSelf();

    Pdf::shouldReceive('view')
        ->with('pdf.credit-note-template', ['data' => ['Number' => '123']])
        ->once()
        ->andReturn($mockBuilder);

    // Test the PDF generation call
    $data = ['Number' => '123'];
    $result = Pdf::view('pdf.credit-note-template', compact('data'))
        ->format('a4')
        ->save('/tmp/test.pdf');

    expect($result)->not->toBeNull();
});

it('verifies pdf template exists', function () {
    expect(view()->exists('pdf.credit-note-template'))->toBeTrue();
});

it('can render pdf template with test data', function () {
    $data = [
        'reference' => 'CN-2024-001',
        'customer' => 'Test Customer',
        'date' => '01/01/2024',
        'type' => 'Credit Note',
        'details' => 'Test product description',
        'net' => '83.33',
        'vat' => '16.67',
        'total' => '100.00',
    ];

    $html = view('pdf.credit-note-template', compact('data'))->render();

    expect($html)
        ->toContain('CN-2024-001')
        ->toContain('Test Customer')
        ->toContain('83.33');
});
