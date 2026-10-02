<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CommerceCustomerResource\Pages;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Lunar\Models\Customer;

class CommerceCustomerResource extends Resource
{
    protected static ?string $model = Customer::class;

    protected static ?string $slug = 'commerce/customers';

    protected static ?string $navigationIcon = 'heroicon-o-users';

    protected static ?string $navigationGroup = 'Commerce';

    protected static ?string $navigationLabel = 'Customers';

    protected static ?int $navigationSort = 2;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Customer')
                    ->schema([
                        Forms\Components\TextInput::make('first_name')
                            ->disabled(),
                        Forms\Components\TextInput::make('last_name')
                            ->disabled(),
                        Forms\Components\TextInput::make('company_name')
                            ->disabled(),
                        Forms\Components\TextInput::make('account_ref')
                            ->disabled(),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('fullName')
                    ->label('Customer')
                    ->searchable(['first_name', 'last_name'])
                    ->sortable(['first_name', 'last_name']),
                Tables\Columns\TextColumn::make('company_name')
                    ->searchable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('account_ref')
                    ->label('Email / Account Ref')
                    ->searchable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('orders_count')
                    ->label('Orders')
                    ->counts('orders')
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCommerceCustomers::route('/'),
            'view' => Pages\ViewCommerceCustomer::route('/{record}'),
        ];
    }
}
