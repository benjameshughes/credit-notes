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
        'row_index'
    ];

    protected $casts = [
        'file_paths' => 'array',
        'row_data' => 'array'
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
}