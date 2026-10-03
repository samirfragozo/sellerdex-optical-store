<?php

namespace App\Filament\Resources\LensOrders\Tables;

use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

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
                TextColumn::make('saleItem.lensConfig.patient.full_name')
                    ->label(__('app.fields.patient'))
                    ->searchable(query: fn (Builder $query, string $search): Builder => $query->whereHas(
                        'saleItem.lensConfig.patient',
                        fn (Builder $patient): Builder => $patient->where('name', 'like', "%{$search}%")->orWhere('last_name', 'like', "%{$search}%"),
                    )),
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
