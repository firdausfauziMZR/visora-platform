<?php

namespace App\Filament\Resources\PhotoIndexingJobResource\Pages;

use App\Filament\Resources\PhotoIndexingJobResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListPhotoIndexingJobs extends ListRecords
{
    protected static string $resource = PhotoIndexingJobResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
