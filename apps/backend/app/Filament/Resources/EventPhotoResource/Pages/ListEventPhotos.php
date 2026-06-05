<?php

namespace App\Filament\Resources\EventPhotoResource\Pages;

use App\Filament\Resources\EventPhotoResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListEventPhotos extends ListRecords
{
    protected static string $resource = EventPhotoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
