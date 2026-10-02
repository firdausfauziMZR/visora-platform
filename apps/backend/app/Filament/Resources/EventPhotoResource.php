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
use Illuminate\Support\HtmlString;
use Illuminate\Support\Facades\Storage;

class EventPhotoResource extends Resource
{
    protected static ?string $model = EventPhoto::class;

    protected static ?string $navigationIcon = 'heroicon-o-photo';

    protected static ?string $navigationGroup = 'Events';

    protected static bool $shouldRegisterNavigation = false;

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
                            ->helperText('Upload one event photo.')
                            ->getUploadedFileUsing(fn (BaseFileUpload $component, string $file, string|array|null $storedFileNames): ?array => static::uploadedPhotoPreview($component, $file, $storedFileNames))
                            ->preserveFilenames()
                            ->required()
                            ->columnSpanFull(),

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
                        Forms\Components\Placeholder::make('variants')
                            ->label('Processed variants')
                            ->content(fn (?EventPhoto $record): HtmlString => static::variantPreviewBlock($record))
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
                Tables\Actions\Action::make('queueIndexing')
                    ->label('Queue Reindexing')
                    ->icon('heroicon-o-cpu-chip')
                    ->requiresConfirmation()
                    ->visible(fn (EventPhoto $record): bool => in_array($record->status, ['uploaded', 'indexed', 'failed'], true))
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
                        ->label('Queue Reindexing')
                        ->icon('heroicon-o-cpu-chip')
                        ->requiresConfirmation()
                        ->action(function ($records, PhotoIndexingDispatcher $dispatcher): void {
                            $records
                                ->filter(fn (EventPhoto $record): bool => in_array($record->status, ['uploaded', 'indexed', 'failed'], true))
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

    protected static function variantPreviewBlock(?EventPhoto $record): HtmlString
    {
        if (! $record || ! $record->event) {
            return new HtmlString('<span class="text-sm text-gray-500 dark:text-gray-400">No variants available.</span>');
        }

        $variants = [
            'thumbnail' => 'Thumbnail',
            'medium' => 'Medium',
            'social_og' => 'Social OG',
        ];

        $cards = collect($variants)
            ->map(function (string $label, string $variant) use ($record): ?string {
                $path = $record->variantPath($variant);

                if (! $path || ! Storage::disk($record->storage_disk)->exists($path)) {
                    return null;
                }

                $url = route('photos.preview', [$record->event, $record, 'variant' => $variant]);
                $metadata = data_get($record->metadata, "variants.{$variant}", []);
                $size = trim(sprintf(
                    '%s%s%s',
                    data_get($metadata, 'width') ?: '',
                    data_get($metadata, 'width') && data_get($metadata, 'height') ? ' x ' : '',
                    data_get($metadata, 'height') ?: '',
                ));

                return sprintf(
                    '<a href="%s" target="_blank" class="block rounded-lg border border-gray-200 bg-white p-3 shadow-sm transition hover:border-primary-500 dark:border-white/10 dark:bg-white/5">
                        <div class="aspect-video overflow-hidden rounded-md bg-gray-100 dark:bg-gray-900">
                            <img src="%s" alt="%s preview" class="h-full w-full object-contain">
                        </div>
                        <div class="mt-2 text-sm font-medium text-gray-950 dark:text-white">%s</div>
                        <div class="text-xs text-gray-500 dark:text-gray-400">%s</div>
                    </a>',
                    e($url),
                    e($url),
                    e($label),
                    e($label),
                    e($size ?: $path),
                );
            })
            ->filter()
            ->implode('');

        if ($cards === '') {
            return new HtmlString('<span class="text-sm text-gray-500 dark:text-gray-400">No processed variants found yet.</span>');
        }

        return new HtmlString(sprintf(
            '<div class="grid gap-4 md:grid-cols-3">%s</div>',
            $cards,
        ));
    }
}
