<?php

namespace App\Filament\Resources\LensOrders\Tables;

use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class LensOrdersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('saleItem.sale.number')
                    ->label(__('app.fields.number'))
                    ->sortable(),
                TextColumn::make('saleItem.sale.customer.name')
                    ->label(__('app.fields.customer'))
                    ->searchable(),
                TextColumn::make('saleItem.description')
                    ->label(__('app.fields.sale_item'))
                    ->searchable(),
                TextColumn::make('supplier.name')
                    ->label(__('app.fields.laboratory'))
                    ->sortable(),
                TextColumn::make('lab_status')
                    ->label(__('app.fields.lab_status'))
                    ->badge(),
                TextColumn::make('expected_date')
                    ->label(__('app.fields.expected_date'))
                    ->date()
                    ->sortable(),
            ])
            ->recordActions([
                EditAction::make(),
            ]);
    }
}
