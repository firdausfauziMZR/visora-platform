<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CommerceProductResource\Pages;
use App\Models\EventPhoto;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\HtmlString;

class CommerceProductResource extends Resource
{
    protected static ?string $model = EventPhoto::class;

    protected static ?string $slug = 'commerce/photo-products';

    protected static ?string $navigationIcon = 'heroicon-o-photo';

    protected static ?string $navigationGroup = 'Commerce';

    protected static ?string $navigationLabel = 'Photo Products';

    protected static ?string $modelLabel = 'photo product';

    protected static ?string $pluralModelLabel = 'photo products';

    protected static ?int $navigationSort = 3;

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->where('status', 'indexed')
            ->with('event');
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Photo Product')
                    ->schema([
                        Forms\Components\Placeholder::make('preview')
                            ->label('Preview')
                            ->content(fn (EventPhoto $record): HtmlString => static::previewImage($record))
                            ->columnSpanFull(),
                        Forms\Components\Placeholder::make('event')
                            ->label('Event')
                            ->content(fn (EventPhoto $record): string => $record->event?->name ?? 'Unknown event'),
                        Forms\Components\Placeholder::make('original_filename')
                            ->label('Photo')
                            ->content(fn (EventPhoto $record): string => $record->original_filename ?: "Photo {$record->id}"),
                        Forms\Components\Placeholder::make('identifier')
                            ->label('Commerce identifier')
                            ->content(fn (EventPhoto $record): string => $record->getIdentifier()),
                        Forms\Components\Placeholder::make('status')
                            ->label('Status')
                            ->content(fn (EventPhoto $record): string => ucfirst($record->status)),
                        Forms\Components\Placeholder::make('faces_detected')
                            ->label('Faces detected')
                            ->content(fn (EventPhoto $record): string => (string) $record->faces_detected),
                        Forms\Components\Placeholder::make('indexed_at')
                            ->label('Indexed at')
                            ->content(fn (EventPhoto $record): string => $record->indexed_at?->toDayDateTimeString() ?? 'Not indexed'),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('event.name')
                    ->label('Event')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('original_filename')
                    ->label('Photo')
                    ->searchable()
                    ->placeholder('Uploaded photo'),
                Tables\Columns\TextColumn::make('identifier')
                    ->label('Identifier')
                    ->state(fn (EventPhoto $record): string => $record->getIdentifier())
                    ->searchable(false)
                    ->toggleable(),
                Tables\Columns\TextColumn::make('download_unlocks_count')
                    ->label('Unlocks')
                    ->counts('downloadUnlocks')
                    ->sortable(),
                Tables\Columns\TextColumn::make('faces_detected')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('indexed_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('event')
                    ->relationship('event', 'name')
                    ->searchable()
                    ->preload(),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCommerceProducts::route('/'),
            'view' => Pages\ViewCommerceProduct::route('/{record}'),
        ];
    }

    protected static function previewImage(EventPhoto $record): HtmlString
    {
        if (! $record->event) {
            return new HtmlString('<span class="text-sm text-gray-500 dark:text-gray-400">No preview available.</span>');
        }

        $variant = $record->variantPath('medium') ? 'medium' : 'thumbnail';
        $url = route('photos.preview', [$record->event, $record, 'variant' => $variant]);
        $alt = $record->original_filename ?: "Photo {$record->id}";

        return new HtmlString(sprintf(
            '<a href="%s" target="_blank" class="block overflow-hidden rounded-lg border border-gray-200 bg-gray-100 dark:border-white/10 dark:bg-gray-900">
                <img src="%s" alt="%s" class="h-auto max-h-[560px] w-full object-contain">
            </a>',
            e($url),
            e($url),
            e($alt),
        ));
    }
}
