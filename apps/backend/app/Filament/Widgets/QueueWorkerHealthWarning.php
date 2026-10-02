<?php

namespace App\Filament\Widgets;

use App\Models\PhotoIndexingJob;
use Filament\Widgets\Widget;

class QueueWorkerHealthWarning extends Widget
{
    protected static string $view = 'filament.widgets.queue-worker-health-warning';

    protected static ?int $sort = 0;

    protected int | string | array $columnSpan = 'full';

    public static function canView(): bool
    {
        return static::staleQueuedCount() > 0 || static::staleRunningCount() > 0;
    }

    protected function getViewData(): array
    {
        return [
            'staleQueuedCount' => static::staleQueuedCount(),
            'staleRunningCount' => static::staleRunningCount(),
        ];
    }

    protected static function staleQueuedCount(): int
    {
        return PhotoIndexingJob::query()
            ->where('status', 'queued')
            ->where('queued_at', '<=', now()->subMinutes(3))
            ->count();
    }

    protected static function staleRunningCount(): int
    {
        return PhotoIndexingJob::query()
            ->where('status', 'running')
            ->where('started_at', '<=', now()->subMinutes(10))
            ->count();
    }
}
