<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\CommerceOrderResource;
use App\Filament\Resources\DownloadUnlockResource;
use App\Filament\Resources\EventResource;
use App\Models\DownloadUnlock;
use App\Models\Event;
use App\Models\EventPhoto;
use App\Models\PhotoIndexingJob;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Lunar\Models\Order;

class VisoraOperationsOverview extends StatsOverviewWidget
{
    protected static ?int $sort = 1;

    protected function getStats(): array
    {
        $totalPhotos = EventPhoto::query()->count();
        $indexedPhotos = EventPhoto::query()->where('status', 'indexed')->count();
        $queuedPhotos = EventPhoto::query()->whereIn('status', ['queued', 'indexing'])->count();
        $failedJobs = PhotoIndexingJob::query()->where('status', 'failed')->count();

        return [
            Stat::make('Active Events', Event::query()->where('is_active', true)->count())
                ->description('Events open for customers')
                ->descriptionIcon('heroicon-o-calendar-days')
                ->color('info')
                ->url(EventResource::getUrl()),
            Stat::make('Indexed Photos', "{$indexedPhotos} / {$totalPhotos}")
                ->description($queuedPhotos > 0 ? "{$queuedPhotos} processing or queued" : 'Ready for search')
                ->descriptionIcon($queuedPhotos > 0 ? 'heroicon-o-arrow-path' : 'heroicon-o-check-circle')
                ->color($queuedPhotos > 0 ? 'warning' : 'success')
                ->url(EventResource::getUrl()),
            Stat::make('Orders', Order::query()->count())
                ->description('Commerce orders')
                ->descriptionIcon('heroicon-o-shopping-bag')
                ->color('success')
                ->url(CommerceOrderResource::getUrl()),
            Stat::make('Downloads', DownloadUnlock::query()->where('status', 'unlocked')->count())
                ->description('Unlocked photo downloads')
                ->descriptionIcon('heroicon-o-lock-open')
                ->color('info')
                ->url(DownloadUnlockResource::getUrl()),
            Stat::make('Failed Indexing', $failedJobs)
                ->description($failedJobs > 0 ? 'Needs review' : 'No failed jobs')
                ->descriptionIcon($failedJobs > 0 ? 'heroicon-o-exclamation-triangle' : 'heroicon-o-check-circle')
                ->color($failedJobs > 0 ? 'danger' : 'success'),
        ];
    }
}
