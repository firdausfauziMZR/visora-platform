<?php

namespace App\Filament\Resources\EventResource\RelationManagers;

use App\Models\User;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class ApprovedCustomersRelationManager extends RelationManager
{
    protected static string $relationship = 'approvedUsers';

    protected static ?string $title = 'Approved Customers';

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
                Tables\Columns\TextColumn::make('pivot.approved_at')
                    ->label('Approved At')
                    ->dateTime()
                    ->sortable(),
            ])
            ->headerActions([
                Tables\Actions\Action::make('approveCustomer')
                    ->label('Approve Customer')
                    ->icon('heroicon-o-user-plus')
                    ->form([
                        Forms\Components\Select::make('user_id')
                            ->label('Customer')
                            ->options(fn (): array => User::query()
                                ->whereDoesntHave('approvedEvents', fn ($query) => $query->whereKey($this->getOwnerRecord()->getKey()))
                                ->orderBy('name')
                                ->pluck('email', 'id')
                                ->all())
                            ->searchable()
                            ->required(),
                    ])
                    ->action(function (array $data): void {
                        $this->getOwnerRecord()->approvedUsers()->syncWithoutDetaching([
                            $data['user_id'] => ['approved_at' => now()],
                        ]);

                        Notification::make()
                            ->title('Customer approved for this event')
                            ->success()
                            ->send();
                    }),
            ])
            ->actions([
                Tables\Actions\Action::make('removeAccess')
                    ->label('Remove Access')
                    ->icon('heroicon-o-lock-closed')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->action(function (User $record): void {
                        $this->getOwnerRecord()->approvedUsers()->detach($record->id);

                        Notification::make()
                            ->title('Customer access removed')
                            ->success()
                            ->send();
                    }),
            ]);
    }
}
