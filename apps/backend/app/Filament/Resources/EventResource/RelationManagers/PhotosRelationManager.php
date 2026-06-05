<?php

namespace App\Filament\Resources\EventResource\RelationManagers;

use App\Models\EventPhoto;
use App\Services\PhotoIndexingDispatcher;
use Filament\Forms;
use Filament\Forms\Components\BaseFileUpload;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Storage;

class PhotosRelationManager extends RelationManager
{
    protected static string $relationship = 'photos';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\FileUpload::make('storage_path')
                    ->label('Images')
                    ->disk('local')
                    ->directory('event-photos')
                    ->image()
                    ->imagePreviewHeight('160')
                    ->getUploadedFileUsing(fn (BaseFileUpload $component, string $file, string|array|null $storedFileNames): ?array => $this->uploadedPhotoPreview($component, $file, $storedFileNames))
                    ->multiple()
                    ->preserveFilenames()
                    ->required(),
                Forms\Components\TextInput::make('original_filename')
                    ->maxLength(255),
                Forms\Components\TextInput::make('storage_disk')
                    ->default('local')
                    ->required()
                    ->maxLength(255),
                Forms\Components\Select::make('status')
                    ->options([
                        'uploaded' => 'Uploaded',
                        'queued' => 'Queued',
                        'indexing' => 'Indexing',
                        'indexed' => 'Indexed',
                        'failed' => 'Failed',
                    ])
                    ->default('uploaded')
                    ->required(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->poll('3s')
            ->recordTitleAttribute('storage_path')
            ->columns([
                Tables\Columns\TextColumn::make('original_filename')
                    ->searchable()
                    ->placeholder('Uploaded photo'),
                Tables\Columns\TextColumn::make('storage_path')
                    ->searchable()
                    ->limit(40)
                    ->toggleable(),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->colors([
                        'gray' => 'uploaded',
                        'warning' => 'queued',
                        'info' => 'indexing',
                        'success' => 'indexed',
                        'danger' => 'failed',
                    ])
                    ->sortable(),
                Tables\Columns\TextColumn::make('faces_detected')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('indexed_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'uploaded' => 'Uploaded',
                        'queued' => 'Queued',
                        'indexing' => 'Indexing',
                        'indexed' => 'Indexed',
                        'failed' => 'Failed',
                    ]),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->using(function (array $data): EventPhoto {
                        $paths = is_array($data['storage_path'])
                            ? $data['storage_path']
                            : [$data['storage_path']];

                        $firstPhoto = null;

                        foreach ($paths as $path) {
                            $photo = $this->getOwnerRecord()->photos()->create([
                                'original_filename' => basename($path),
                                'storage_disk' => $data['storage_disk'] ?? 'local',
                                'storage_path' => $path,
                                'status' => $data['status'] ?? 'uploaded',
                            ]);

                            $firstPhoto ??= $photo;
                        }

                        return $firstPhoto;
                    }),
            ])
            ->actions([
                Tables\Actions\Action::make('runIndexingNow')
                    ->label('Run Now')
                    ->icon('heroicon-o-bolt')
                    ->requiresConfirmation()
                    ->action(function (EventPhoto $record, PhotoIndexingDispatcher $dispatcher): void {
                        $job = $dispatcher->runNow($record);

                        Notification::make()
                            ->title('Photo indexed')
                            ->body("Detected {$job->photo->faces_detected} face(s).")
                            ->success()
                            ->send();
                    }),
                Tables\Actions\Action::make('queueIndexing')
                    ->label('Queue')
                    ->icon('heroicon-o-cpu-chip')
                    ->requiresConfirmation()
                    ->visible(fn (EventPhoto $record): bool => in_array($record->status, ['uploaded', 'failed'], true))
                    ->action(function (EventPhoto $record, PhotoIndexingDispatcher $dispatcher): void {
                        $dispatcher->queue($record);

                        Notification::make()
                            ->title('Photo queued for indexing')
                            ->success()
                            ->send();
                    }),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\BulkAction::make('runIndexingNow')
                        ->label('Run Indexing Now')
                        ->icon('heroicon-o-bolt')
                        ->requiresConfirmation()
                        ->action(function ($records, PhotoIndexingDispatcher $dispatcher): void {
                            $indexedCount = 0;

                            $records->each(function (EventPhoto $record) use ($dispatcher, &$indexedCount): void {
                                $dispatcher->runNow($record);
                                $indexedCount++;
                            });

                            Notification::make()
                                ->title("Indexed {$indexedCount} photo(s)")
                                ->success()
                                ->send();
                        }),
                    Tables\Actions\BulkAction::make('queueIndexing')
                        ->label('Queue Indexing')
                        ->icon('heroicon-o-cpu-chip')
                        ->requiresConfirmation()
                        ->action(function ($records, PhotoIndexingDispatcher $dispatcher): void {
                            $queuedCount = 0;

                            $records
                                ->filter(fn (EventPhoto $record): bool => in_array($record->status, ['uploaded', 'failed'], true))
                                ->each(function (EventPhoto $record) use ($dispatcher, &$queuedCount): void {
                                    $dispatcher->queue($record);
                                    $queuedCount++;
                                });

                            Notification::make()
                                ->title("Queued {$queuedCount} photo(s) for indexing")
                                ->success()
                                ->send();
                        }),
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    protected function uploadedPhotoPreview(BaseFileUpload $component, string $file, string|array|null $storedFileNames): ?array
    {
        $record = $component->getRecord();

        if (! $record instanceof EventPhoto || ! $record->event) {
            return null;
        }

        $storage = Storage::disk($record->storage_disk);

        if (! $storage->exists($file)) {
            return null;
        }

        return [
            'name' => is_string($storedFileNames) ? $storedFileNames : ($record->original_filename ?: basename($file)),
            'size' => $storage->size($file),
            'type' => $storage->mimeType($file),
            'url' => route('photos.preview', [$record->event, $record]),
        ];
    }
}
