<?php

namespace App\Filament\Resources;

use App\Filament\Resources\EventPhotoResource\Pages;
use App\Models\EventPhoto;
use App\Services\PhotoIndexingDispatcher;
use Filament\Forms;
use Filament\Forms\Components\BaseFileUpload;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Storage;

class EventPhotoResource extends Resource
{
    protected static ?string $model = EventPhoto::class;

    protected static ?string $navigationIcon = 'heroicon-o-photo';

    protected static ?string $navigationGroup = 'Events';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Photo')
                    ->schema([
                        Forms\Components\Select::make('event_id')
                            ->relationship('event', 'name')
                            ->searchable()
                            ->preload()
                            ->required(),
                        Forms\Components\FileUpload::make('storage_path')
                            ->label('Image')
                            ->disk('local')
                            ->directory('event-photos')
                            ->image()
                            ->imagePreviewHeight('160')
                            ->getUploadedFileUsing(fn (BaseFileUpload $component, string $file, string|array|null $storedFileNames): ?array => self::uploadedPhotoPreview($component, $file, $storedFileNames))
                            ->preserveFilenames()
                            ->required(),
                        Forms\Components\TextInput::make('original_filename')
                            ->maxLength(255),
                        Forms\Components\TextInput::make('storage_disk')
                            ->default('local')
                            ->required()
                            ->maxLength(255),
                        Forms\Components\TextInput::make('mime_type')
                            ->maxLength(255),
                        Forms\Components\TextInput::make('file_size')
                            ->numeric()
                            ->minValue(0),
                    ])
                    ->columns(2),
                Forms\Components\Section::make('Indexing')
                    ->schema([
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
                        Forms\Components\TextInput::make('faces_detected')
                            ->numeric()
                            ->minValue(0)
                            ->default(0),
                        Forms\Components\DateTimePicker::make('indexed_at')
                            ->label('Indexed At'),
                        Forms\Components\KeyValue::make('metadata')
                            ->columnSpanFull(),
                    ])
                    ->columns(3),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->poll('3s')
            ->columns([
                Tables\Columns\TextColumn::make('event.name')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('original_filename')
                    ->searchable()
                    ->placeholder('Uploaded photo'),
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
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('event')
                    ->relationship('event', 'name')
                    ->searchable()
                    ->preload(),
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'uploaded' => 'Uploaded',
                        'queued' => 'Queued',
                        'indexing' => 'Indexing',
                        'indexed' => 'Indexed',
                        'failed' => 'Failed',
                    ]),
            ])
            ->actions([
                Tables\Actions\Action::make('runIndexingNow')
                    ->label('Run Indexing Now')
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
                    ->label('Queue Indexing')
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
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\BulkAction::make('queueIndexing')
                        ->label('Queue Indexing')
                        ->icon('heroicon-o-cpu-chip')
                        ->requiresConfirmation()
                        ->action(function ($records, PhotoIndexingDispatcher $dispatcher): void {
                            $records
                                ->filter(fn (EventPhoto $record): bool => in_array($record->status, ['uploaded', 'failed'], true))
                                ->each(fn (EventPhoto $record) => $dispatcher->queue($record));

                            Notification::make()
                                ->title('Selected photos queued for indexing')
                                ->success()
                                ->send();
                        }),
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListEventPhotos::route('/'),
            'create' => Pages\CreateEventPhoto::route('/create'),
            'edit' => Pages\EditEventPhoto::route('/{record}/edit'),
        ];
    }

    protected static function uploadedPhotoPreview(BaseFileUpload $component, string $file, string|array|null $storedFileNames): ?array
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
