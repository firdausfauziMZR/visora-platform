<x-filament-widgets::widget>
    <x-filament::section>
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div class="flex gap-3">
                <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-warning-500/10 text-warning-500">
                    <x-filament::icon
                        icon="heroicon-o-exclamation-triangle"
                        class="h-6 w-6"
                    />
                </div>

                <div class="space-y-1">
                    <h2 class="text-base font-semibold text-gray-950 dark:text-white">
                        Queue worker may not be running
                    </h2>

                    <p class="text-sm text-gray-500 dark:text-gray-400">
                        {{ $staleQueuedCount }} queued indexing job(s) have not started, and {{ $staleRunningCount }} running job(s) look stale.
                    </p>

                    <p class="text-sm text-gray-500 dark:text-gray-400">
                        Start the local admin server with the worker:
                        <code class="rounded bg-gray-950/5 px-1.5 py-0.5 text-xs dark:bg-white/10">composer run dev:admin</code>
                    </p>
                </div>
            </div>
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
