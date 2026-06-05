<?php

namespace App\Filament\Resources\DownloadUnlockResource\Pages;

use App\Filament\Resources\DownloadUnlockResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListDownloadUnlocks extends ListRecords
{
    protected static string $resource = DownloadUnlockResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
