<?php

namespace App\Filament\Resources\EventPhotoResource\Pages;

use App\Filament\Resources\EventPhotoResource;
use App\Models\EventPhoto;
use App\Services\PhotoIndexingDispatcher;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditEventPhoto extends EditRecord
{
    protected static string $resource = EventPhotoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('runIndexingNow')
                ->label('Run Indexing Now')
                ->icon('heroicon-o-bolt')
                ->requiresConfirmation()
                ->action(function (): void {
                    $job = app(PhotoIndexingDispatcher::class)->runNow($this->record);

                    Notification::make()
                        ->title('Photo indexed')
                        ->body("Detected {$job->photo->faces_detected} face(s).")
                        ->success()
                        ->send();

                    $this->redirect(static::getResource()::getUrl('edit', [
                        'record' => $this->record,
                    ]));
                }),
            Actions\Action::make('queueIndexing')
                ->label('Queue Indexing')
                ->icon('heroicon-o-cpu-chip')
                ->requiresConfirmation()
                ->visible(fn (): bool => $this->record instanceof EventPhoto
                    && in_array($this->record->status, ['uploaded', 'failed'], true))
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
