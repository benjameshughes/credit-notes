<?php

namespace App\Providers;

use App\Events\BatchCompleted;
use App\Events\PdfJobCompleted;
use App\Listeners\CheckBatchCompletion;
use App\Listeners\CreateBatchDownload;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
    /**
     * The event to listener mappings for the application.
     */
    protected $listen = [
        BatchCompleted::class => [
            CreateBatchDownload::class,
        ],
    ];

    /**
     * Register services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        //
    }
}
