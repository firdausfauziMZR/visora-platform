<?php

namespace App\Filament\Resources\EventResource\RelationManagers;

use App\Filament\Resources\EventPhotoResource;
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
use Illuminate\Support\HtmlString;

class PhotosRelationManager extends RelationManager
{
    protected static string $relationship = 'photos';

    public function form(Form $form): Form
    {
        return $form
            ->schema($this->photoUploadForm());
    }

    public function table(Table $table): Table
    {
        return $table
            ->poll('3s')
            ->header(fn (): HtmlString => $this->indexingProgressHeader())
            ->recordTitleAttribute('storage_path')
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->label('ID')
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('original_filename')
                    ->label('Filename')
                    ->searchable()
                    ->sortable()
                    ->url(fn (EventPhoto $record): string => EventPhotoResource::getUrl('edit', ['record' => $record]))
                    ->placeholder('Uploaded photo'),
                Tables\Columns\TextColumn::make('storage_path')
                    ->label('Storage Path')
                    ->searchable()
                    ->limit(40)
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('status')
                    ->label('Indexing')
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
                    ->label('Faces')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('indexed_at')
                    ->label('Indexed At')
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
                Tables\Actions\Action::make('uploadPhotos')
                    ->label('Upload Photos')
                    ->icon('heroicon-o-arrow-up-tray')
                    ->color('primary')
                    ->modalHeading('Upload event photos')
                    ->modalSubmitActionLabel('Upload')
                    ->form(fn (): array => $this->photoUploadForm())
                    ->action(fn (array $data, PhotoIndexingDispatcher $dispatcher): null => $this->storeUploadedPhotos($data, $dispatcher)),
            ])
            ->emptyStateActions([
                Tables\Actions\Action::make('uploadPhotosEmpty')
                    ->label('Upload Photos')
                    ->icon('heroicon-o-arrow-up-tray')
                    ->color('primary')
                    ->modalHeading('Upload event photos')
                    ->modalSubmitActionLabel('Upload')
                    ->form(fn (): array => $this->photoUploadForm())
                    ->action(fn (array $data, PhotoIndexingDispatcher $dispatcher): null => $this->storeUploadedPhotos($data, $dispatcher)),
            ])
            ->actions([
                Tables\Actions\Action::make('queueIndexing')
                    ->label('Reindex')
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
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\BulkAction::make('queueIndexing')
                        ->label('Queue Reindexing')
                        ->icon('heroicon-o-cpu-chip')
                        ->requiresConfirmation()
                        ->action(function ($records, PhotoIndexingDispatcher $dispatcher): void {
                            $queuedCount = 0;

                            $records
                                ->filter(fn (EventPhoto $record): bool => in_array($record->status, ['uploaded', 'indexed', 'failed'], true))
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

    /**
     * @return array<int, Forms\Components\Component>
     */
    protected function photoUploadForm(): array
    {
        return [
            Forms\Components\FileUpload::make('storage_path')
                ->label('Photos')
                ->disk('local')
                ->directory('event-photos')
                ->image()
                ->helperText('Batch upload shows filenames for faster review.')
                ->getUploadedFileUsing(fn (BaseFileUpload $component, string $file, string|array|null $storedFileNames): ?array => $this->uploadedPhotoPreview($component, $file, $storedFileNames))
                ->multiple()
                ->preserveFilenames()
                ->panelLayout(null)
                ->maxParallelUploads(4)
                ->extraAttributes(['class' => 'visora-batch-photo-upload'])
                ->previewable(false)
                ->required()
                ->columnSpanFull(),

            Forms\Components\Hidden::make('storage_disk')
                ->default('local')
                ->required(),
            Forms\Components\Hidden::make('status')
                ->default('uploaded')
                ->required(),
        ];
    }

    protected function storeUploadedPhotos(array $data, PhotoIndexingDispatcher $dispatcher): null
    {
        $paths = is_array($data['storage_path'])
            ? $data['storage_path']
            : [$data['storage_path']];

        foreach ($paths as $path) {
            $photo = $this->getOwnerRecord()->photos()->create([
                'original_filename' => basename($path),
                'storage_disk' => $data['storage_disk'] ?? 'local',
                'storage_path' => $path,
                'status' => $data['status'] ?? 'uploaded',
            ]);

            if ($photo->status === 'uploaded') {
                $dispatcher->queue($photo);
            }
        }

        Notification::make()
            ->title('Photos uploaded')
            ->body(sprintf(
                '%d photo(s) uploaded and queued for indexing.',
                count($paths),
            ))
            ->success()
            ->send();

        return null;
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

    protected function indexingProgressHeader(): HtmlString
    {
        $counts = $this->getOwnerRecord()
            ->photos()
            ->selectRaw('status, count(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        $total = (int) $counts->sum();
        $uploaded = (int) ($counts['uploaded'] ?? 0);
        $queued = (int) ($counts['queued'] ?? 0);
        $indexing = (int) ($counts['indexing'] ?? 0);
        $indexed = (int) ($counts['indexed'] ?? 0);
        $failed = (int) ($counts['failed'] ?? 0);
        $waiting = $uploaded + $queued;
        $donePercent = $total > 0 ? (int) round(($indexed / $total) * 100) : 0;

        return new HtmlString(sprintf(
            '<div class="space-y-3 rounded-lg border border-gray-200 bg-white p-4 dark:border-white/10 dark:bg-white/5">
                <div class="flex flex-wrap items-center gap-2 text-sm">
                    %s
                    %s
                    %s
                    %s
                    %s
                </div>
                <div class="h-2 overflow-hidden rounded-full bg-gray-200 dark:bg-white/10">
                    <div class="h-full rounded-full bg-emerald-500 transition-all" style="width: %d%%"></div>
                </div>
            </div>',
            $this->progressPill('Total', $total),
            $this->progressPill('Waiting', $waiting, 'warning'),
            $this->progressPill('Indexing', $indexing, 'info'),
            $this->progressPill('Indexed', $indexed, 'success'),
            $this->progressPill('Failed', $failed, 'danger'),
            $donePercent,
        ));
    }

    protected function progressPill(string $label, int $count, string $tone = 'neutral'): string
    {
        $classes = match ($tone) {
            'warning' => 'bg-amber-100 text-amber-800 dark:bg-amber-500/15 dark:text-amber-300',
            'info' => 'bg-sky-100 text-sky-800 dark:bg-sky-500/15 dark:text-sky-300',
            'success' => 'bg-emerald-100 text-emerald-800 dark:bg-emerald-500/15 dark:text-emerald-300',
            'danger' => 'bg-red-100 text-red-800 dark:bg-red-500/15 dark:text-red-300',
            default => 'bg-gray-100 text-gray-800 ring-1 ring-gray-200 dark:bg-gray-900 dark:text-gray-100 dark:ring-white/20',
        };

        return sprintf(
            '<span class="inline-flex items-center gap-1 rounded-md px-2.5 py-1 font-medium %s"><span>%s</span><span class="font-semibold">%d</span></span>',
            $classes,
            e($label),
            $count,
        );
    }
}
