<?php

namespace App\Providers;

use App\Filament\Lunar\Extensions\DashboardExtension;
use App\Filament\Lunar\Widgets\VisoraAverageOrderValueChart;
use App\Filament\Lunar\Widgets\VisoraNewVsReturningCustomersChart;
use App\Filament\Lunar\Widgets\VisoraOrdersSalesChart;
use App\Filament\Lunar\Widgets\VisoraOrderStatsOverview;
use App\Filament\Lunar\Widgets\VisoraOrderTotalsChart;
use App\Models\EventPhoto;
use App\Observers\EventPhotoObserver;
use Filament\Navigation\MenuItem;
use Illuminate\Support\ServiceProvider;
use Lunar\Admin\Filament\Pages\Dashboard;
use Lunar\Admin\Support\Facades\LunarPanel;
use Lunar\Facades\Telemetry;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        LunarPanel::extensions([
            Dashboard::class => DashboardExtension::class,
        ])
            ->panel(fn ($panel) => $panel->widgets([
                VisoraOrderStatsOverview::class,
                VisoraOrderTotalsChart::class,
                VisoraOrdersSalesChart::class,
                VisoraAverageOrderValueChart::class,
                VisoraNewVsReturningCustomersChart::class,
            ])->userMenuItems([
                'main-site' => MenuItem::make()
                    ->label('Main Site')
                    ->icon('heroicon-o-home')
                    ->url(fn (): string => route('events.index')),
            ]))
            ->register();
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Telemetry::optOut();

        EventPhoto::observe(EventPhotoObserver::class);
    }
}
