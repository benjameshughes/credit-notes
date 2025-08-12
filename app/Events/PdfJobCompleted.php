<?php

namespace App\Events;

use App\Models\PdfGenerationJob;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PdfJobCompleted
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public PdfGenerationJob $job
    ) {}
}