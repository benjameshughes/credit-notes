<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Barryvdh\DomPDF\Facade\Pdf;

class TestPdfGeneration extends Command
{
    protected $signature = 'pdf:test';
    protected $description = 'Test PDF generation with debugging info';

    public function handle()
    {
        $this->info('Testing PDF generation...');
        
        try {
            // DomPDF doesn't require external dependencies
            $this->info('✅ Using DomPDF - Pure PHP PDF generation (no external dependencies needed)');
            
            // Test data
            $testData = [
                'reference' => 'TEST-001',
                'customer' => 'Test Customer Ltd',
                'date' => '2025-01-15',
                'type' => 'Credit Note',
                'net' => '100.00',
                'vat' => '20.00',
                'total' => '120.00',
                'details' => 'Test credit note for diagnostics'
            ];
            
            $this->info('Test data: ' . json_encode($testData));
            
            // Test PDF generation
            $this->info('Attempting to generate test PDF...');
            $testPath = storage_path('app/test-pdf.pdf');
            
            $pdf = Pdf::loadView('pdf.credit-note-template', ['data' => $testData])
                ->setPaper('a4', 'portrait');
                
            $pdf->save($testPath);
                
            if (file_exists($testPath)) {
                $fileSize = filesize($testPath);
                $this->info("✅ PDF generated successfully!");
                $this->info("File location: {$testPath}");
                $this->info("File size: {$fileSize} bytes");
                
                // Clean up test file
                unlink($testPath);
            } else {
                $this->error("❌ PDF file was not created");
            }
            
        } catch (\Exception $e) {
            $this->error('❌ PDF generation failed:');
            $this->error('Error: ' . $e->getMessage());
            $this->error('File: ' . $e->getFile() . ':' . $e->getLine());
            
            // Show more details for debugging
            $this->newLine();
            $this->error('Full stack trace:');
            $this->error($e->getTraceAsString());
        }
    }
}