<div class="max-w-4xl mx-auto p-6" wire:poll.100ms="loadJobs">
    <div class="mb-8">
        <h1 class="text-3xl font-bold text-zinc-900 dark:text-zinc-100">CSV to PDF Processor</h1>
    </div>

    {{-- Upload Form --}}
    <div class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-700 rounded-lg p-6 mb-8">
        <form wire:submit="uploadCsv" class="flex justify-between items-center">
            <flux:field>
                <flux:label>Upload CSV File</flux:label>
                <flux:input
                        type="file"
                        wire:model="csvFile"
                        accept=".csv,.txt"
                />
                <flux:error name="csvFile"/>
            </flux:field>

            <flux:button
                    type="submit"
                    variant="primary"
                    wire:loading.attr="disabled"
                    wire:target="csvFile,uploadCsv"
                    icon="arrow-up-tray"
            >
                <span wire:loading.remove wire:target="uploadCsv">
                    Generate PDFs
                </span>
                <span wire:loading wire:target="uploadCsv">
                    <flux:icon.arrow-path class="size-4 mr-2 animate-spin"/>
                    Processing...
                </span>
            </flux:button>
        </form>
    </div>

    {{-- Messages --}}
    @if($message)
        <div class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-700 rounded-lg p-6 mb-6">
            @if($messageType === 'success')
                <div class="flex items-center p-4 text-green-100 bg-green-800/20 border border-green-700/50 rounded-lg">
                    <flux:icon.check-circle class="size-5 mr-3"/>
                    {{ $message }}
                </div>
            @else
                <div class="flex items-center p-4 text-red-100 bg-red-800/20 border border-red-700/50 rounded-lg">
                    <flux:icon.x-circle class="size-5 mr-3"/>
                    {{ $message }}
                </div>
            @endif
        </div>
    @endif

    {{-- Active/Processing Jobs --}}
    @if(!empty($processingJobs))
        <div class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-700 rounded-lg p-6 mb-8">
            <div class="mb-6">
                <h2 class="text-xl font-semibold text-zinc-900 dark:text-zinc-100 flex items-center">
                    <flux:icon.arrow-path class="size-5 mr-2 animate-spin text-blue-500"/>
                    Active Jobs ({{ count($processingJobs) }})
                </h2>
            </div>

            <div class="space-y-4">
                @foreach($processingJobs as $job)
                    <div class="bg-zinc-50 dark:bg-zinc-900/50 border border-zinc-200 dark:border-zinc-700 rounded-lg p-6">
                        <div class="flex justify-between items-start mb-4">
                            <div>
                                <h3 class="text-lg font-medium text-zinc-900 dark:text-zinc-100 mb-1">{{ $job['filename'] }}</h3>
                                <p class="text-sm text-zinc-500 dark:text-zinc-400">{{ $job['created_at'] }}</p>
                            </div>
                            <div class="text-right">
                                @if($job['status'] === 'completed')
                                    <flux:badge variant="lime" size="sm">
                                        <flux:icon.check-circle class="size-3 mr-1"/>
                                        Completed
                                    </flux:badge>
                                @elseif($job['status'] === 'completed_with_errors')
                                    <flux:badge variant="yellow" size="sm">
                                        <flux:icon.exclamation-triangle class="size-3 mr-1"/>
                                        Completed with Errors
                                    </flux:badge>
                                @elseif($job['status'] === 'processing')
                                    <flux:badge variant="blue" size="sm">
                                        <flux:icon.arrow-path class="size-3 mr-1 animate-spin"/>
                                        Processing
                                    </flux:badge>
                                @elseif($job['status'] === 'pending')
                                    <flux:badge variant="zinc" size="sm">
                                        <flux:icon.clock class="size-3 mr-1"/>
                                        Pending
                                    </flux:badge>
                                @else
                                    <flux:badge variant="red" size="sm">
                                        <flux:icon.x-circle class="size-3 mr-1"/>
                                        Failed
                                    </flux:badge>
                                @endif
                            </div>
                        </div>

                        {{-- Stats Grid --}}
                        <div class="grid grid-cols-4 gap-4 mb-4 text-sm">
                            <div class="text-center p-3 bg-zinc-100 dark:bg-zinc-800 rounded-lg">
                                <div class="text-2xl font-bold text-zinc-900 dark:text-zinc-100">{{ $job['total_rows'] }}</div>
                                <div class="text-zinc-500 dark:text-zinc-400">Total</div>
                            </div>
                            <div class="text-center p-3 bg-green-50 dark:bg-green-900/20 rounded-lg">
                                <div class="text-2xl font-bold text-green-600 dark:text-green-400">{{ $job['completed_rows'] }}</div>
                                <div class="text-green-500 dark:text-green-400/70">Completed</div>
                            </div>
                            <div class="text-center p-3 bg-blue-50 dark:bg-blue-900/20 rounded-lg">
                                <div class="text-2xl font-bold text-blue-600 dark:text-blue-400">{{ $job['processing_rows'] }}</div>
                                <div class="text-blue-500 dark:text-blue-400/70">Processing</div>
                            </div>
                            <div class="text-center p-3 bg-red-50 dark:bg-red-900/20 rounded-lg">
                                <div class="text-2xl font-bold text-red-600 dark:text-red-400">{{ $job['failed_rows'] }}</div>
                                <div class="text-red-500 dark:text-red-400/70">Failed</div>
                            </div>
                        </div>

                        {{-- Progress Bar --}}
                        @if($job['status'] === 'processing' || $job['status'] === 'completed' || $job['status'] === 'completed_with_errors')
                            <div class="mb-4">
                                <div class="flex justify-between items-center mb-2">
                                    <span class="text-sm text-zinc-600 dark:text-zinc-300">Progress</span>
                                    <span class="text-sm font-medium text-zinc-900 dark:text-zinc-100">{{ $job['progress'] }}%</span>
                                </div>
                                <div class="w-full bg-zinc-200 dark:bg-zinc-700 rounded-full h-2">
                                    <div
                                            class="bg-blue-600 dark:bg-blue-500 h-2 rounded-full transition-all duration-300"
                                            style="width: {{ $job['progress'] }}%"
                                    ></div>
                                </div>
                            </div>
                        @endif

                        {{-- Action Buttons --}}
                        @if($job['status'] === 'completed' || $job['status'] === 'completed_with_errors')
                            <div class="flex justify-end gap-3">
                                @if($job['download_available'])
                                    <flux:button
                                            type="button"
                                            variant="primary"
                                            icon="arrow-down-tray"
                                            href="{{ route('download', $job['batch_id']) }}"
                                    >
                                        @if($job['is_single_pdf'])
                                            Download PDF
                                        @else
                                            Download ZIP
                                        @endif
                                    </flux:button>
                                @endif

                                <flux:button
                                        type="button"
                                        variant="danger"
                                        icon="trash"
                                        wire:click="deleteJob('{{ $job['batch_id'] }}')"
                                        wire:confirm="Are you sure you want to delete this job and all associated files? This action cannot be undone."
                                >
                                    Delete
                                </flux:button>
                            </div>
                        @endif
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    {{-- Completed Jobs --}}
    <div class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-700 rounded-lg p-6">
        <div class="mb-6">
            <h2 class="text-xl font-semibold text-zinc-900 dark:text-zinc-100 flex items-center">
                <flux:icon.check-circle class="size-5 mr-2 text-green-500"/>
                Completed Jobs ({{ count($completedJobs) }})
            </h2>
        </div>

        @if(empty($completedJobs))
            <div class="text-center py-12">
                @if(empty($processingJobs))
                    <flux:icon.document-arrow-up class="size-12 text-zinc-400 dark:text-zinc-500 mx-auto mb-4"/>
                    <p class="text-zinc-500 dark:text-zinc-400">No jobs yet. Upload a CSV file to get started!</p>
                @else
                    <flux:icon.clock class="size-12 text-zinc-400 dark:text-zinc-500 mx-auto mb-4"/>
                    <p class="text-zinc-500 dark:text-zinc-400">No completed jobs yet. Check back once your active jobs
                        finish processing.</p>
                @endif
            </div>
        @else
            <div class="space-y-4">
                @foreach($completedJobs as $job)
                    <div class="bg-zinc-50 dark:bg-zinc-900/50 border border-zinc-200 dark:border-zinc-700 rounded-lg p-6">
                        <div class="flex justify-between items-start mb-4">
                            <div>
                                <h3 class="text-lg font-medium text-zinc-900 dark:text-zinc-100 mb-1">{{ $job['filename'] }}</h3>
                                <p class="text-sm text-zinc-500 dark:text-zinc-400">{{ $job['created_at'] }}</p>
                            </div>
                            <div class="text-right">
                                @if($job['status'] === 'completed')
                                    <flux:badge variant="lime" size="sm">
                                        <flux:icon.check-circle class="size-3 mr-1"/>
                                        Completed
                                    </flux:badge>
                                @elseif($job['status'] === 'completed_with_errors')
                                    <flux:badge variant="yellow" size="sm">
                                        <flux:icon.exclamation-triangle class="size-3 mr-1"/>
                                        Completed with Errors
                                    </flux:badge>
                                @endif
                            </div>
                        </div>

                        {{-- Stats Grid --}}
                        <div class="grid grid-cols-4 gap-4 mb-4 text-sm">
                            <div class="text-center p-3 bg-zinc-100 dark:bg-zinc-800 rounded-lg">
                                <div class="text-2xl font-bold text-zinc-900 dark:text-zinc-100">{{ $job['total_rows'] }}</div>
                                <div class="text-zinc-500 dark:text-zinc-400">Total</div>
                            </div>
                            <div class="text-center p-3 bg-green-50 dark:bg-green-900/20 rounded-lg">
                                <div class="text-2xl font-bold text-green-600 dark:text-green-400">{{ $job['completed_rows'] }}</div>
                                <div class="text-green-500 dark:text-green-400/70">Completed</div>
                            </div>
                            <div class="text-center p-3 bg-blue-50 dark:bg-blue-900/20 rounded-lg">
                                <div class="text-2xl font-bold text-blue-600 dark:text-blue-400">{{ $job['processing_rows'] }}</div>
                                <div class="text-blue-500 dark:text-blue-400/70">Processing</div>
                            </div>
                            <div class="text-center p-3 bg-red-50 dark:bg-red-900/20 rounded-lg">
                                <div class="text-2xl font-bold text-red-600 dark:text-red-400">{{ $job['failed_rows'] }}</div>
                                <div class="text-red-500 dark:text-red-400/70">Failed</div>
                            </div>
                        </div>

                        {{-- Action Buttons --}}
                        <div class="flex justify-end gap-3">
                            @if($job['download_available'])
                                <flux:button
                                        type="button"
                                        icon="arrow-down-tray"
                                        variant="primary"
                                        href="{{ route('download', $job['batch_id']) }}"
                                >
                                    @if($job['is_single_pdf'])
                                        Download PDF
                                    @else
                                        Download ZIP
                                    @endif
                                </flux:button>
                            @endif

                            <flux:button
                                    type="button"
                                    icon="trash"
                                    variant="danger"
                                    wire:click="deleteJob('{{ $job['batch_id'] }}')"
                                    wire:confirm="Are you sure you want to delete this job and all associated files? This action cannot be undone."
                            >
                                Delete
                            </flux:button>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</div>


