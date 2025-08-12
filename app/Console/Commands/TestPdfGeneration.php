<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Spatie\LaravelPdf\Facades\Pdf;

class TestPdfGeneration extends Command
{
    protected $signature = 'pdf:test';
    protected $description = 'Test PDF generation with debugging info';

    public function handle()
    {
        $this->info('Testing PDF generation...');
        
        try {
            // Check if Node.js is available
            $this->info('Checking Node.js...');
            $nodeVersion = shell_exec('node --version 2>&1');
            $this->info('Node.js version: ' . ($nodeVersion ?: 'NOT FOUND'));
            
            // Check if npm is available
            $this->info('Checking npm...');
            $npmVersion = shell_exec('npm --version 2>&1');
            $this->info('npm version: ' . ($npmVersion ?: 'NOT FOUND'));
            
            // Check Chromium/Chrome
            $this->info('Checking for Chrome/Chromium...');
            $chromeVersion = shell_exec('google-chrome --version 2>&1') ?: 
                           shell_exec('chromium-browser --version 2>&1') ?: 
                           shell_exec('chrome --version 2>&1') ?: 
                           'NOT FOUND';
            $this->info('Chrome/Chromium: ' . $chromeVersion);
            
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
            
            $pdf = Pdf::view('pdf.credit-note-template', ['data' => $testData])
                ->format('a4');
                
            // Add server-safe Chrome arguments
            $chromeArgs = config('browsershot.chrome_arguments', [
                '--no-sandbox',
                '--disable-dev-shm-usage', 
                '--disable-gpu',
                '--single-process',
            ]);
            
            $this->info('Chrome arguments: ' . implode(' ', $chromeArgs));
            
            foreach ($chromeArgs as $arg) {
                $pdf->addChromiumArguments([$arg]);
            }
            
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