<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PhotoIndexingJobResource\Pages;
use App\Models\PhotoIndexingJob;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class PhotoIndexingJobResource extends Resource
{
    protected static ?string $model = PhotoIndexingJob::class;

    protected static ?string $navigationIcon = 'heroicon-o-cpu-chip';

    protected static ?string $navigationGroup = 'Events';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Job')
                    ->schema([
                        Forms\Components\Select::make('event_id')
                            ->relationship('event', 'name')
                            ->searchable()
                            ->preload()
                            ->required(),
                        Forms\Components\Select::make('event_photo_id')
                            ->relationship('photo', 'storage_path')
                            ->searchable()
                            ->preload()
                            ->required(),
                        Forms\Components\Select::make('status')
                            ->options([
                                'queued' => 'Queued',
                                'running' => 'Running',
                                'completed' => 'Completed',
                                'failed' => 'Failed',
                            ])
                            ->default('queued')
                            ->required(),
                        Forms\Components\TextInput::make('attempts')
                            ->numeric()
                            ->minValue(0)
                            ->default(0),
                        Forms\Components\TextInput::make('ml_status')
                            ->maxLength(255),
                        Forms\Components\DateTimePicker::make('queued_at')
                            ->default(now())
                            ->required(),
                        Forms\Components\DateTimePicker::make('started_at'),
                        Forms\Components\DateTimePicker::make('completed_at'),
                        Forms\Components\Textarea::make('error_message')
                            ->rows(3)
                            ->columnSpanFull(),
                        Forms\Components\KeyValue::make('response_payload')
                            ->columnSpanFull(),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('event.name')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('photo.storage_path')
                    ->label('Photo')
                    ->searchable()
                    ->limit(40),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->colors([
                        'warning' => 'queued',
                        'info' => 'running',
                        'success' => 'completed',
                        'danger' => 'failed',
                    ])
                    ->sortable(),
                Tables\Columns\TextColumn::make('attempts')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('ml_status')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('queued_at')
                    ->dateTime()
                    ->sortable(),
                Tables\Columns\TextColumn::make('completed_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('event')
                    ->relationship('event', 'name')
                    ->searchable()
                    ->preload(),
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'queued' => 'Queued',
                        'running' => 'Running',
                        'completed' => 'Completed',
                        'failed' => 'Failed',
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
            'index' => Pages\ListPhotoIndexingJobs::route('/'),
            'create' => Pages\CreatePhotoIndexingJob::route('/create'),
            'edit' => Pages\EditPhotoIndexingJob::route('/{record}/edit'),
        ];
    }
}
