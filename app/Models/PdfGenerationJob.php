<?php

// app/Models/PdfGenerationJob.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PdfGenerationJob extends Model
{
    use HasFactory;

    protected $fillable = [
        'batch_id',
        'original_filename',
        'total_rows',
        'processed_rows',
        'failed_rows',
        'status',
        'download_status',
        'file_paths',
        'zip_path',
        'single_pdf_path',
        'row_data',
        'row_index',
        'user_id',
    ];

    protected $casts = [
        'file_paths' => 'array',
        'row_data' => 'array',
    ];

    public function getProgressPercentageAttribute()
    {
        if ($this->total_rows <= 0) {
            return 0;
        }

        $percentage = round((($this->processed_rows ?? 0) / $this->total_rows) * 100, 2);

        // Return integer if it's a whole number
        return $percentage == (int) $percentage ? (int) $percentage : $percentage;
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function delete()
    {
        // Delete associated files before deleting the record
        $this->deleteAssociatedFiles();

        return parent::delete();
    }

    public function deleteAssociatedFiles()
    {
        // Delete individual PDF files
        if ($this->file_paths && is_array($this->file_paths)) {
            foreach ($this->file_paths as $filePath) {
                $fullPath = storage_path('app/'.$filePath);
                if (file_exists($fullPath)) {
                    unlink($fullPath);
                }
            }
        }

        // Delete ZIP file
        if ($this->zip_path) {
            $fullZipPath = storage_path('app/'.$this->zip_path);
            if (file_exists($fullZipPath)) {
                unlink($fullZipPath);
            }
        }

        // Delete single PDF file
        if ($this->single_pdf_path) {
            $fullSinglePdfPath = storage_path('app/'.$this->single_pdf_path);
            if (file_exists($fullSinglePdfPath)) {
                unlink($fullSinglePdfPath);
            }
        }

        // Delete batch directory if empty
        if ($this->batch_id) {
            $batchDir = storage_path("app/pdfs/{$this->batch_id}");
            if (is_dir($batchDir)) {
                $files = array_diff(scandir($batchDir), ['.', '..']);
                if (empty($files)) {
                    rmdir($batchDir);
                }
            }
        }
    }
}
