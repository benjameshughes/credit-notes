<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class JobProgressUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public string $batchId,
        public string $status,
        public int $totalRows,
        public int $completedRows,
        public int $failedRows,
        public int $processingRows,
        public ?string $zipPath = null,
        public ?string $singlePdfPath = null
    ) {}

    public function broadcastOn(): array
    {
        return [
            new Channel('batch.'.$this->batchId),
        ];
    }

    public function broadcastAs(): string
    {
        return 'job.progress.updated';
    }

    public function broadcastWith(): array
    {
        $progress = $this->totalRows > 0 ? round((($this->completedRows + $this->failedRows) / $this->totalRows) * 100, 1) : 0;

        // Determine overall batch status
        if ($this->completedRows + $this->failedRows === $this->totalRows) {
            $overallStatus = $this->failedRows > 0 ? 'completed_with_errors' : 'completed';
        } elseif ($this->processingRows > 0 || $this->completedRows > 0) {
            $overallStatus = 'processing';
        } else {
            $overallStatus = 'pending';
        }

        $data = [
            'batch_id' => $this->batchId,
            'status' => $overallStatus,
            'total_rows' => $this->totalRows,
            'completed_rows' => $this->completedRows,
            'failed_rows' => $this->failedRows,
            'processing_rows' => $this->processingRows,
            'progress' => $progress,
            'zip_path' => $this->zipPath,
            'single_pdf_path' => $this->singlePdfPath,
            'download_available' => $this->zipPath || $this->singlePdfPath,
            'is_single_pdf' => ! $this->zipPath && $this->singlePdfPath,
        ];

        // Log broadcasting for debugging
        \Log::info("Broadcasting progress update for batch {$this->batchId}", $data);

        return $data;
    }
}
