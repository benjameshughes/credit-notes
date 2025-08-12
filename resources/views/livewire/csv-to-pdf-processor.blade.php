<div class="max-w-4xl mx-auto p-6" wire:poll.100ms="loadJobs">
    <div class="mb-8">
        <h1 class="text-3xl font-bold text-zinc-900 dark:text-zinc-100">CSV to PDF Processor</h1>
    </div>

    {{-- Upload Form --}}
    @if(!$showMapping)
    <div class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-700 rounded-lg p-6 mb-8">
        <form wire:submit="uploadCsv" class="flex justify-between items-center">
            <flux:field>
                <flux:label>Upload CSV File</flux:label>
                <flux:input
                        type="file"
                        wire:model="csvFile"
                        accept=".csv"
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
                    Upload & Map Fields
                </span>
                <span wire:loading wire:target="uploadCsv">
                    <flux:icon.arrow-path class="size-4 mr-2 animate-spin"/>
                    Processing...
                </span>
            </flux:button>
        </form>
    </div>
    @endif

    {{-- Field Mapping Section --}}
    @if($showMapping)
    <div class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-700 rounded-lg p-6 mb-8">
        <div class="mb-6">
            <h2 class="text-xl font-semibold text-zinc-900 dark:text-zinc-100 mb-2">Map Your CSV Fields</h2>
            <p class="text-sm text-zinc-600 dark:text-zinc-400">Match your CSV columns to the PDF template fields below.</p>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
            {{-- Field Mapping --}}
            <div>
                <h3 class="text-lg font-medium text-zinc-900 dark:text-zinc-100 mb-4">Field Mapping</h3>
                <div class="space-y-4">
                    @foreach($csvHeaders as $header)
                    <div class="flex items-center space-x-4">
                        <div class="w-1/2">
                            <span class="text-sm font-medium text-zinc-700 dark:text-zinc-300">{{ $header }}</span>
                        </div>
                        <div class="w-1/2">
                            <flux:select wire:model="fieldMapping.{{ $header }}" size="sm">
                                <option value="">-- Skip Field --</option>
                                <option value="reference">Reference</option>
                                <option value="date">Date</option>
                                <option value="type">Type</option>
                                <option value="customer">Customer</option>
                                <option value="details">Details/Description</option>
                                <option value="net">Net Amount</option>
                                <option value="vat">VAT Amount</option>
                                <option value="total">Total Amount</option>
                                <option value="discount">Discount Amount</option>
                            </flux:select>
                        </div>
                    </div>
                    @endforeach
                </div>
            </div>

            {{-- Preview Data --}}
            <div>
                <h3 class="text-lg font-medium text-zinc-900 dark:text-zinc-100 mb-4">Data Preview</h3>
                <div class="bg-zinc-50 dark:bg-zinc-800 rounded-lg p-4 overflow-x-auto">
                    <table class="w-full text-xs">
                        <thead>
                            <tr class="border-b border-zinc-200 dark:border-zinc-700">
                                @foreach($csvHeaders as $header)
                                <th class="text-left py-2 px-2 font-medium text-zinc-700 dark:text-zinc-300">{{ $header }}</th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody>
                            @foreach(array_slice($csvPreview, 0, 3) as $row)
                            <tr class="border-b border-zinc-100 dark:border-zinc-700">
                                @foreach($csvHeaders as $header)
                                <td class="py-2 px-2 text-zinc-600 dark:text-zinc-400">{{ Str::limit($row[$header] ?? '', 20) }}</td>
                                @endforeach
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="flex justify-end gap-3 mt-6">
            <flux:button
                    type="button"
                    variant="ghost"
                    wire:click="$set('showMapping', false)"
            >
                Cancel
            </flux:button>
            <flux:button
                    type="button"
                    variant="primary"
                    wire:click="confirmMapping"
                    wire:loading.attr="disabled"
                    wire:target="confirmMapping"
                    icon="check"
            >
                <span wire:loading.remove wire:target="confirmMapping">
                    Generate PDFs
                </span>
                <span wire:loading wire:target="confirmMapping">
                    <flux:icon.arrow-path class="size-4 mr-2 animate-spin"/>
                    Processing...
                </span>
            </flux:button>
        </div>
    </div>
    @endif

    {{-- Toast Notifications --}}
    @if(count($toasts) > 0)
        <div class="fixed top-4 right-4 z-50 space-y-2">
            @foreach($toasts as $toast)
                <div 
                    class="flex items-center min-w-80 p-4 rounded-lg shadow-lg border backdrop-blur-sm animate-in slide-in-from-right-full duration-300
                    @if($toast['type'] === 'success') 
                        bg-green-50/95 dark:bg-green-900/95 border-green-200 dark:border-green-800 text-green-800 dark:text-green-200
                    @else
                        bg-red-50/95 dark:bg-red-900/95 border-red-200 dark:border-red-800 text-red-800 dark:text-red-200
                    @endif"
                    x-data="{ show: true }" 
                    x-show="show"
                    x-init="setTimeout(() => show = false, 4000); setTimeout(() => $wire.removeToast('{{ $toast['id'] }}'), 4500)"
                >
                    @if($toast['type'] === 'success')
                        <flux:icon.check-circle class="size-5 mr-3 flex-shrink-0"/>
                    @else
                        <flux:icon.x-circle class="size-5 mr-3 flex-shrink-0"/>
                    @endif
                    
                    <span class="flex-1">{{ $toast['message'] }}</span>
                    
                    <button 
                        wire:click="removeToast('{{ $toast['id'] }}')"
                        class="ml-2 flex-shrink-0 opacity-60 hover:opacity-100 transition-opacity"
                    >
                        <flux:icon.x-mark class="size-4"/>
                    </button>
                </div>
            @endforeach
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
                                @elseif($job['status'] === 'packaging')
                                    <flux:badge variant="purple" size="sm">
                                        <flux:icon.gift class="size-3 mr-1 animate-bounce"/>
                                        Packaging Your Files
                                    </flux:badge>
                                @elseif($job['status'] === 'processing')
                                    <flux:badge variant="blue" size="sm">
                                        <flux:icon.arrow-path class="size-3 mr-1 animate-spin"/>
                                        Processing PDFs
                                    </flux:badge>
                                @elseif($job['status'] === 'pending')
                                    <flux:badge variant="zinc" size="sm">
                                        <flux:icon.clock class="size-3 mr-1"/>
                                        Pending
                                    </flux:badge>
                                @elseif($job['status'] === 'paused')
                                    <flux:badge variant="orange" size="sm">
                                        <flux:icon.pause class="size-3 mr-1"/>
                                        Paused
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
                        <div class="grid grid-cols-5 gap-3 mb-4 text-sm">
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
                            <div class="text-center p-3 bg-orange-50 dark:bg-orange-900/20 rounded-lg">
                                <div class="text-2xl font-bold text-orange-600 dark:text-orange-400">{{ $job['paused_rows'] ?? 0 }}</div>
                                <div class="text-orange-500 dark:text-orange-400/70">Paused</div>
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
                        @elseif($job['status'] === 'packaging')
                            <div class="mb-4">
                                <div class="flex justify-between items-center mb-2">
                                    <span class="text-sm text-zinc-600 dark:text-zinc-300">🎁 Wrapping up your files...</span>
                                    <span class="text-sm font-medium text-purple-600 dark:text-purple-400">Almost done!</span>
                                </div>
                                <div class="w-full bg-purple-200 dark:bg-purple-900/30 rounded-full h-2 overflow-hidden">
                                    <div class="bg-gradient-to-r from-purple-500 to-purple-600 dark:from-purple-400 dark:to-purple-500 h-2 rounded-full animate-pulse" style="width: 100%"></div>
                                </div>
                                <div class="text-center mt-2">
                                    <span class="text-xs text-purple-600 dark:text-purple-400">Creating your download package with care ✨</span>
                                </div>
                            </div>
                        @endif

                        {{-- Action Buttons --}}
                        <div class="flex justify-end gap-3">
                            @if($job['status'] === 'pending')
                                {{-- Pause button for pending jobs --}}
                                <flux:button
                                        type="button"
                                        variant="ghost"
                                        icon="pause"
                                        wire:click="pauseJob('{{ $job['batch_id'] }}')"
                                >
                                    Pause Job
                                </flux:button>
                                
                                {{-- Force remove button for stuck jobs --}}
                                <flux:button
                                        type="button"
                                        variant="danger"
                                        icon="x-mark"
                                        wire:click="confirmForceRemove('{{ $job['batch_id'] }}')"
                                >
                                    Force Remove
                                </flux:button>
                            @elseif($job['status'] === 'paused')
                                {{-- Resume button for paused jobs --}}
                                <flux:button
                                        type="button"
                                        variant="primary"
                                        icon="play"
                                        wire:click="resumeJob('{{ $job['batch_id'] }}')"
                                >
                                    Resume Job
                                </flux:button>
                                
                                {{-- Force remove button --}}
                                <flux:button
                                        type="button"
                                        variant="danger"
                                        icon="x-mark"
                                        wire:click="confirmForceRemove('{{ $job['batch_id'] }}')"
                                >
                                    Force Remove
                                </flux:button>
                            @elseif($job['status'] === 'processing')
                                {{-- Pause button for processing jobs --}}
                                <flux:button
                                        type="button"
                                        variant="ghost"
                                        icon="pause"
                                        wire:click="pauseJob('{{ $job['batch_id'] }}')"
                                >
                                    Pause Job
                                </flux:button>
                                
                                {{-- Force remove for processing jobs --}}
                                <flux:button
                                        type="button"
                                        variant="danger"
                                        icon="x-mark"
                                        wire:click="confirmForceRemove('{{ $job['batch_id'] }}')"
                                >
                                    Force Remove
                                </flux:button>
                            @elseif($job['status'] === 'packaging')
                                {{-- Only force remove for packaging jobs that might be stuck --}}
                                <flux:button
                                        type="button"
                                        variant="danger"
                                        icon="x-mark"
                                        wire:click="confirmForceRemove('{{ $job['batch_id'] }}')"
                                >
                                    Force Remove
                                </flux:button>
                            @elseif($job['status'] === 'completed' || $job['status'] === 'completed_with_errors')
                                {{-- Download and delete buttons for completed jobs --}}
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
                            @else
                                {{-- Failed or unknown status jobs - force remove option --}}
                                <flux:button
                                        type="button"
                                        variant="danger"
                                        icon="x-mark"
                                        wire:click="confirmForceRemove('{{ $job['batch_id'] }}')"
                                >
                                    Remove
                                </flux:button>
                            @endif
                        </div>
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
                                @elseif($job['status'] === 'download_failed')
                                    <flux:badge variant="red" size="sm">
                                        <flux:icon.x-circle class="size-3 mr-1"/>
                                        Download Failed
                                    </flux:badge>
                                @endif
                            </div>
                        </div>

                        {{-- Stats Grid --}}
                        <div class="grid grid-cols-5 gap-3 mb-4 text-sm">
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
                            <div class="text-center p-3 bg-orange-50 dark:bg-orange-900/20 rounded-lg">
                                <div class="text-2xl font-bold text-orange-600 dark:text-orange-400">{{ $job['paused_rows'] ?? 0 }}</div>
                                <div class="text-orange-500 dark:text-orange-400/70">Paused</div>
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

    {{-- Force Remove Confirmation Modal --}}
    <flux:modal name="force-remove-modal" class="max-w-md">
            <div class="space-y-6">
                {{-- Modal Header --}}
                <div class="flex items-start space-x-4">
                    <div class="flex-shrink-0">
                        <div class="flex items-center justify-center w-12 h-12 mx-auto bg-red-100 rounded-full dark:bg-red-900/20">
                            <flux:icon.exclamation-triangle class="w-6 h-6 text-red-600 dark:text-red-400" />
                        </div>
                    </div>
                    <div class="flex-1 min-w-0">
                        <h3 class="text-lg font-semibold text-zinc-900 dark:text-zinc-100">
                            Force Remove Job
                        </h3>
                        <p class="mt-2 text-sm text-zinc-600 dark:text-zinc-400">
                            This action will forcefully remove the job and all associated files. This should only be used for stuck or failed jobs.
                        </p>
                    </div>
                </div>

                {{-- Warning Content --}}
                <div class="bg-red-50 dark:bg-red-900/10 border border-red-200 dark:border-red-800 rounded-lg p-4">
                    <div class="flex items-start space-x-3">
                        <flux:icon.shield-exclamation class="w-5 h-5 text-red-500 dark:text-red-400 flex-shrink-0 mt-0.5" />
                        <div class="text-sm">
                            <p class="font-medium text-red-800 dark:text-red-200 mb-1">
                                ⚠️ This action cannot be undone
                            </p>
                            <ul class="text-red-700 dark:text-red-300 space-y-1 ml-4">
                                <li>• Job will be permanently removed from the database</li>
                                <li>• All generated PDF files will be deleted</li>
                                <li>• ZIP downloads will become unavailable</li>
                                <li>• Progress tracking will be lost</li>
                            </ul>
                        </div>
                    </div>
                </div>

                {{-- Action Buttons --}}
                <div class="flex justify-end space-x-3 pt-4">
                    <flux:button
                        variant="ghost"
                        wire:click="cancelForceRemove"
                    >
                        Cancel
                    </flux:button>
                    <flux:button
                        variant="danger"
                        icon="trash"
                        wire:click="executeForceRemove"
                        wire:loading.attr="disabled"
                        wire:target="executeForceRemove"
                    >
                        <span wire:loading.remove wire:target="executeForceRemove">
                            Force Remove Job
                        </span>
                        <span wire:loading wire:target="executeForceRemove">
                            <flux:icon.arrow-path class="w-4 h-4 mr-2 animate-spin" />
                            Removing...
                        </span>
                    </flux:button>
                </div>
            </div>
        </flux:modal>
</div>


