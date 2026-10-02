<?php

namespace App\Filament\Resources\EventResource\RelationManagers;

use App\Models\User;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class PendingAccessRequestsRelationManager extends RelationManager
{
    protected static string $relationship = 'pendingAccessRequests';

    protected static ?string $title = 'Pending Access Requests';

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('email')
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('email')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('pivot.created_at')
                    ->label('Requested At')
                    ->dateTime()
                    ->sortable(),
            ])
            ->actions([
                Tables\Actions\Action::make('approve')
                    ->label('Approve')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->action(function (User $record): void {
                        $this->getOwnerRecord()->pendingAccessRequests()->updateExistingPivot($record->id, [
                            'approved_at' => now(),
                        ]);

                        Notification::make()
                            ->title('Customer approved for this event')
                            ->success()
                            ->send();
                    }),
                Tables\Actions\Action::make('deny')
                    ->label('Deny')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->action(function (User $record): void {
                        $this->getOwnerRecord()->pendingAccessRequests()->detach($record->id);

                        Notification::make()
                            ->title('Access request denied')
                            ->success()
                            ->send();
                    }),
            ]);
    }
}
