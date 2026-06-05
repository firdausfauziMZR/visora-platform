<?php

namespace App\Filament\Resources;

use App\Filament\Resources\DownloadUnlockResource\Pages;
use App\Models\DownloadUnlock;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class DownloadUnlockResource extends Resource
{
    protected static ?string $model = DownloadUnlock::class;

    protected static ?string $navigationIcon = 'heroicon-o-lock-open';

    protected static ?string $navigationGroup = 'Commerce';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Unlock')
                    ->schema([
                        Forms\Components\Select::make('event_photo_id')
                            ->relationship('photo', 'storage_path')
                            ->searchable()
                            ->preload()
                            ->required(),
                        Forms\Components\Select::make('user_id')
                            ->relationship('user', 'email')
                            ->searchable()
                            ->preload(),
                        Forms\Components\TextInput::make('order_reference')
                            ->maxLength(255),
                        Forms\Components\TextInput::make('customer_email')
                            ->email()
                            ->maxLength(255),
                        Forms\Components\Select::make('status')
                            ->options([
                                'unlocked' => 'Unlocked',
                                'revoked' => 'Revoked',
                                'expired' => 'Expired',
                            ])
                            ->default('unlocked')
                            ->required(),
                        Forms\Components\DateTimePicker::make('unlocked_at')
                            ->default(now()),
                        Forms\Components\DateTimePicker::make('expires_at'),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('photo.event.name')
                    ->label('Event')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('photo.storage_path')
                    ->label('Photo')
                    ->searchable()
                    ->limit(40),
                Tables\Columns\TextColumn::make('customer_email')
                    ->searchable(),
                Tables\Columns\TextColumn::make('order_reference')
                    ->searchable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->colors([
                        'success' => 'unlocked',
                        'danger' => 'revoked',
                        'gray' => 'expired',
                    ])
                    ->sortable(),
                Tables\Columns\TextColumn::make('unlocked_at')
                    ->dateTime()
                    ->sortable(),
                Tables\Columns\TextColumn::make('expires_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'unlocked' => 'Unlocked',
                        'revoked' => 'Revoked',
                        'expired' => 'Expired',
                    ]),
            ])
            ->actions([
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
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListDownloadUnlocks::route('/'),
            'create' => Pages\CreateDownloadUnlock::route('/create'),
            'edit' => Pages\EditDownloadUnlock::route('/{record}/edit'),
        ];
    }
}
