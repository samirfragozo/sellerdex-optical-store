<?php

namespace App\Filament\Resources\Prescriptions\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/** The armados (and their sales) made on this prescription. */
class LensConfigsRelationManager extends RelationManager
{
    protected static string $relationship = 'lensConfigs';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('app.relations.sales');
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('saleItem.sale.number')
                    ->label(__('app.fields.number')),
                TextColumn::make('saleItem.sale.customer.full_name')
                    ->label(__('app.fields.customer')),
                TextColumn::make('patient.full_name')
                    ->label(__('app.fields.patient')),
                TextColumn::make('saleItem.sale.status')
                    ->label(__('app.fields.status'))
                    ->badge(),
                TextColumn::make('saleItem.sale.sold_at')
                    ->label(__('app.fields.sold_at'))
                    ->date(),
            ]);
    }
}
