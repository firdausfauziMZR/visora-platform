<?php

namespace App\Filament\Resources\EventPhotoResource\Pages;

use App\Filament\Resources\EventResource;
use App\Filament\Resources\EventPhotoResource;
use App\Models\EventPhoto;
use App\Services\PhotoIndexingDispatcher;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditEventPhoto extends EditRecord
{
    protected static string $resource = EventPhotoResource::class;

    public function getBreadcrumbs(): array
    {
        $eventName = $this->record?->event?->name ?? 'Event';
        $filename = $this->record?->original_filename ?: 'Photo';

        return [
            EventResource::getUrl('index') => 'Events',
            EventResource::getUrl('edit', ['record' => $this->record->event_id]) => $eventName,
            '#' => $filename,
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('backToEvent')
                ->label('Back to Event')
                ->icon('heroicon-o-arrow-left')
                ->color('gray')
                ->url(fn (): string => EventResource::getUrl('edit', ['record' => $this->record->event_id])),
            Actions\Action::make('queueIndexing')
                ->label('Queue Reindexing')
                ->icon('heroicon-o-cpu-chip')
                ->requiresConfirmation()
                ->visible(fn (): bool => $this->record instanceof EventPhoto
                    && in_array($this->record->status, ['uploaded', 'indexed', 'failed'], true))
                ->action(function (): void {
                    app(PhotoIndexingDispatcher::class)->queue($this->record);

                    Notification::make()
                        ->title('Photo queued for indexing')
                        ->success()
                        ->send();

                    $this->redirect(static::getResource()::getUrl('edit', [
                        'record' => $this->record,
                    ]));
                }),
            Actions\DeleteAction::make(),
        ];
    }
}
