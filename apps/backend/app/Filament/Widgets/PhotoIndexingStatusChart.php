<?php

namespace App\Filament\Widgets;

use App\Models\EventPhoto;
use Filament\Widgets\ChartWidget;

class PhotoIndexingStatusChart extends ChartWidget
{
    protected static ?string $heading = 'Photo Indexing';

    protected static ?string $description = 'Current processing state of uploaded photos.';

    protected static ?int $sort = 3;

    protected static ?string $maxHeight = '300px';

    protected function getData(): array
    {
        $statuses = [
            'uploaded' => 'Uploaded',
            'queued' => 'Queued',
            'indexing' => 'Indexing',
            'indexed' => 'Indexed',
            'failed' => 'Failed',
        ];

        $counts = EventPhoto::query()
            ->selectRaw('status, COUNT(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status');

        return [
            'datasets' => [
                [
                    'data' => array_map(fn (string $status): int => (int) ($counts[$status] ?? 0), array_keys($statuses)),
                    'backgroundColor' => [
                        '#6b7280',
                        '#f59e0b',
                        '#3b82f6',
                        '#10b981',
                        '#ef4444',
                    ],
                ],
            ],
            'labels' => array_values($statuses),
        ];
    }

    protected function getType(): string
    {
        return 'doughnut';
    }
}
