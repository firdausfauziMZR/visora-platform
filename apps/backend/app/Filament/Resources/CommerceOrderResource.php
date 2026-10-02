<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CommerceOrderResource\Pages;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Lunar\Models\Order;

class CommerceOrderResource extends Resource
{
    protected static ?string $model = Order::class;

    protected static ?string $slug = 'commerce/orders';

    protected static ?string $navigationIcon = 'heroicon-o-shopping-bag';

    protected static ?string $navigationGroup = 'Commerce';

    protected static ?string $navigationLabel = 'Orders';

    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Order')
                    ->schema([
                        Forms\Components\TextInput::make('reference')
                            ->disabled(),
                        Forms\Components\TextInput::make('status')
                            ->disabled(),
                        Forms\Components\TextInput::make('currency_code')
                            ->label('Currency')
                            ->disabled(),
                        Forms\Components\DateTimePicker::make('placed_at')
                            ->disabled(),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('reference')
                    ->searchable()
                    ->sortable()
                    ->placeholder('Draft order'),
                Tables\Columns\TextColumn::make('customer.fullName')
                    ->label('Customer')
                    ->searchable(['first_name', 'last_name'])
                    ->placeholder('Guest'),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->sortable(),
                Tables\Columns\TextColumn::make('total')
                    ->label('Total')
                    ->formatStateUsing(fn (mixed $state): string => method_exists($state, 'formatted') ? $state->formatted() : (string) $state),
                Tables\Columns\TextColumn::make('placed_at')
                    ->dateTime()
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options(fn (): array => collect(config('lunar.orders.statuses', []))
                        ->mapWithKeys(fn (array $status, string $key): array => [$key => $status['label'] ?? $key])
                        ->all()),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCommerceOrders::route('/'),
            'view' => Pages\ViewCommerceOrder::route('/{record}'),
        ];
    }
}
