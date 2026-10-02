<?php

namespace App\Filament\Widgets;

use Carbon\CarbonPeriod;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Carbon;
use Lunar\Models\Order;

class RevenueTrendChart extends ChartWidget
{
    protected static ?string $heading = 'Revenue';

    protected static ?string $description = 'Order total by day, last 14 days.';

    protected static ?int $sort = 2;

    protected static ?string $maxHeight = '300px';

    protected function getData(): array
    {
        $start = now()->subDays(13)->startOfDay();
        $end = now()->endOfDay();

        $totals = Order::query()
            ->whereBetween('created_at', [$start, $end])
            ->selectRaw('DATE(created_at) as order_date, SUM(total) as total_cents')
            ->groupBy('order_date')
            ->pluck('total_cents', 'order_date');

        $labels = [];
        $data = [];

        foreach (CarbonPeriod::create($start, $end) as $date) {
            $key = $date->toDateString();
            $labels[] = Carbon::parse($date)->format('M j');
            $data[] = round(((int) ($totals[$key] ?? 0)) / 100, 2);
        }

        return [
            'datasets' => [
                [
                    'label' => 'Revenue',
                    'data' => $data,
                    'borderColor' => '#f59e0b',
                    'backgroundColor' => 'rgba(245, 158, 11, 0.16)',
                    'fill' => true,
                    'tension' => 0.35,
                ],
            ],
            'labels' => $labels,
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }
}
