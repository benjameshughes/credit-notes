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
        'file_paths',
        'zip_path',
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
        if ($this->file_paths) {
            foreach ($this->file_paths as $filePath) {
                if (file_exists($filePath)) {
                    unlink($filePath);
                }
            }
        }

        // Delete ZIP file
        if ($this->zip_path && file_exists($this->zip_path)) {
            unlink($this->zip_path);
        }

        // Delete batch directory if empty
        if ($this->batch_id) {
            $batchDir = storage_path("app/pdfs/{$this->batch_id}");
            if (is_dir($batchDir) && count(scandir($batchDir)) === 2) { // Only . and ..
                rmdir($batchDir);
            }
        }
    }
}
