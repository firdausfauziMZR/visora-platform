<?php

namespace App\Filament\Lunar\Extensions;

use App\Filament\Lunar\Widgets\VisoraAverageOrderValueChart;
use App\Filament\Lunar\Widgets\VisoraNewVsReturningCustomersChart;
use App\Filament\Lunar\Widgets\VisoraOrdersSalesChart;
use App\Filament\Lunar\Widgets\VisoraOrderStatsOverview;
use App\Filament\Lunar\Widgets\VisoraOrderTotalsChart;
use Lunar\Admin\Filament\Widgets\Dashboard\Orders\AverageOrderValueChart;
use Lunar\Admin\Filament\Widgets\Dashboard\Orders\NewVsReturningCustomersChart;
use Lunar\Admin\Filament\Widgets\Dashboard\Orders\OrdersSalesChart;
use Lunar\Admin\Filament\Widgets\Dashboard\Orders\OrderStatsOverview;
use Lunar\Admin\Filament\Widgets\Dashboard\Orders\OrderTotalsChart;
use Lunar\Admin\Support\Extending\BaseExtension;

class DashboardExtension extends BaseExtension
{
    public function getOverviewWidgets(array $widgets): array
    {
        return $this->replaceWidgets($widgets);
    }

    public function getChartWidgets(array $widgets): array
    {
        return $this->replaceWidgets($widgets);
    }

    /**
     * @param  array<int, class-string>  $widgets
     * @return array<int, class-string>
     */
    private function replaceWidgets(array $widgets): array
    {
        return array_map(
            fn (string $widget): string => match ($widget) {
                OrderStatsOverview::class => VisoraOrderStatsOverview::class,
                OrderTotalsChart::class => VisoraOrderTotalsChart::class,
                OrdersSalesChart::class => VisoraOrdersSalesChart::class,
                AverageOrderValueChart::class => VisoraAverageOrderValueChart::class,
                NewVsReturningCustomersChart::class => VisoraNewVsReturningCustomersChart::class,
                default => $widget,
            },
            $widgets,
        );
    }
}
