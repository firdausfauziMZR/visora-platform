<?php

namespace App\Filament\Resources\DownloadUnlockResource\Pages;

use App\Filament\Resources\DownloadUnlockResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditDownloadUnlock extends EditRecord
{
    protected static string $resource = DownloadUnlockResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
