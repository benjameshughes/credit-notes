<?php

use Barryvdh\DomPDF\Facade\Pdf;
use Barryvdh\DomPDF\PDF as DomPDFWrapper;

it('can use DomPDF facade', function () {
    // Mock the PDF facade to test integration
    $mockWrapper = Mockery::mock(DomPDFWrapper::class);
    $mockWrapper->shouldReceive('setPaper')->with('a4', 'portrait')->once()->andReturnSelf();
    $mockWrapper->shouldReceive('save')->with(Mockery::type('string'))->once()->andReturnSelf();

    Pdf::shouldReceive('loadView')
        ->with('pdf.credit-note-template', ['data' => ['Number' => '123']])
        ->once()
        ->andReturn($mockWrapper);

    // Test the PDF generation call
    $data = ['Number' => '123'];
    $result = Pdf::loadView('pdf.credit-note-template', compact('data'))
        ->setPaper('a4', 'portrait')
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