<?php

namespace App\Filament\Resources;

use App\Filament\Resources\EventResource\Pages;
use App\Filament\Resources\EventResource\RelationManagers\ApprovedCustomersRelationManager;
use App\Filament\Resources\EventResource\RelationManagers\PendingAccessRequestsRelationManager;
use App\Filament\Resources\EventResource\RelationManagers\PhotosRelationManager;
use App\Models\Event;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;

class EventResource extends Resource
{
    protected static ?string $model = Event::class;

    protected static ?string $navigationIcon = 'heroicon-o-calendar-days';

    protected static ?string $navigationGroup = 'Events';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Event Details')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->required()
                            ->maxLength(255)
                            ->live(onBlur: true)
                            ->afterStateUpdated(fn (string $operation, $state, Forms\Set $set) => $operation === 'create'
                                ? $set('slug', Str::slug($state))
                                : null),
                        Forms\Components\TextInput::make('slug')
                            ->required()
                            ->maxLength(255)
                            ->unique(ignoreRecord: true),
                        Forms\Components\TextInput::make('access_code')
                            ->label('Access Code')
                            ->helperText('Share this code with customers who should unlock this event.')
                            ->default(fn (): string => Event::generateAccessCode())
                            ->dehydrateStateUsing(fn (?string $state): ?string => Event::normalizeAccessCode($state))
                            ->maxLength(32)
                            ->unique(ignoreRecord: true),
                        Forms\Components\Select::make('access_mode')
                            ->label('Access Mode')
                            ->options(Event::accessModeOptions())
                            ->default(Event::ACCESS_MODE_CODE_GRANTS_ACCESS)
                            ->required()
                            ->helperText('Controls what happens when a customer scans the QR code or enters the event code.'),
                        Forms\Components\Placeholder::make('access_links')
                            ->label('QR Access')
                            ->content(function (?Event $record): HtmlString {
                                if (! $record?->access_code) {
                                    return new HtmlString('Save the event to generate a QR link.');
                                }

                                $accessUrl = e(route('event-access-codes.show', $record->access_code));
                                $qrUrl = e(route('events.access-qr', $record));

                                return new HtmlString(<<<HTML
                                    <div style="display:grid; gap:0.5rem;">
                                        <a href="{$accessUrl}" target="_blank" rel="noreferrer">Open customer access link</a>
                                        <a href="{$qrUrl}" target="_blank" rel="noreferrer">Open QR code</a>
                                        <code style="word-break:break-all;">{$accessUrl}</code>
                                    </div>
                                HTML);
                            })
                            ->columnSpanFull(),
                        Forms\Components\DateTimePicker::make('starts_at')
                            ->label('Starts At'),
                        Forms\Components\DateTimePicker::make('ends_at')
                            ->label('Ends At'),
                        Forms\Components\TextInput::make('venue')
                            ->maxLength(255),
                        Forms\Components\Textarea::make('description')
                            ->rows(4)
                            ->columnSpanFull(),
                        Forms\Components\Toggle::make('is_active')
                            ->label('Active')
                            ->default(true),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('slug')
                    ->searchable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('access_code')
                    ->label('Access Code')
                    ->searchable()
                    ->copyable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('access_mode')
                    ->label('Access Mode')
                    ->formatStateUsing(fn (?string $state): string => Event::accessModeOptions()[$state] ?? 'QR/code grants access')
                    ->badge()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('starts_at')
                    ->dateTime()
                    ->sortable(),
                Tables\Columns\TextColumn::make('venue')
                    ->searchable()
                    ->toggleable(),
                Tables\Columns\IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean(),
                Tables\Columns\TextColumn::make('photos_count')
                    ->counts('photos')
                    ->label('Photos')
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Active'),
            ])
            ->actions([
                Tables\Actions\Action::make('qr')
                    ->label('QR')
                    ->icon('heroicon-o-qr-code')
                    ->url(fn (Event $record): string => route('events.access-qr', $record))
                    ->openUrlInNewTab(),
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            ApprovedCustomersRelationManager::class,
            PendingAccessRequestsRelationManager::class,
            PhotosRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListEvents::route('/'),
            'create' => Pages\CreateEvent::route('/create'),
            'edit' => Pages\EditEvent::route('/{record}/edit'),
        ];
    }
}
