<?php

namespace Database\Factories;

use App\Models\PdfGenerationJob;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class PdfGenerationJobFactory extends Factory
{
    protected $model = PdfGenerationJob::class;

    public function definition(): array
    {
        return [
            'batch_id' => Str::uuid(),
            'original_filename' => $this->faker->randomElement(['orders.csv', 'invoices.csv', 'credit_notes.csv']),
            'total_rows' => $this->faker->numberBetween(1, 100),
            'processed_rows' => 0,
            'failed_rows' => 0,
            'status' => 'pending',
            'file_paths' => null,
            'zip_path' => null,
        ];
    }

    public function processing(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'processing',
            'processed_rows' => $this->faker->numberBetween(1, $attributes['total_rows'] - 1),
        ]);
    }

    public function completed(): static
    {
        return $this->state(function (array $attributes) {
            $totalRows = $attributes['total_rows'];
            $processedRows = $this->faker->numberBetween($totalRows - 5, $totalRows);

            return [
                'status' => 'completed',
                'processed_rows' => $processedRows,
                'failed_rows' => $totalRows - $processedRows,
                'file_paths' => $this->faker->randomElements([
                    'pdfs/batch-1/credit_note_1.pdf',
                    'pdfs/batch-1/credit_note_2.pdf',
                    'pdfs/batch-1/credit_note_3.pdf',
                ], $processedRows),
                'zip_path' => 'downloads/credit_notes_'.date('Y-m-d_H-i-s').'.zip',
            ];
        });
    }

    public function failed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'failed',
            'processed_rows' => $this->faker->numberBetween(0, $attributes['total_rows']),
            'failed_rows' => $this->faker->numberBetween(1, $attributes['total_rows']),
        ]);
    }
}
