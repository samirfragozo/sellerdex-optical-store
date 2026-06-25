<?php

namespace App\Filament\Resources\Prescriptions\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class SalesRelationManager extends RelationManager
{
    protected static string $relationship = 'sales';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('app.relations.sales');
    }

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('sold_at', 'desc')
            ->columns([
                TextColumn::make('number')
                    ->label(__('app.fields.number'))
                    ->searchable(),
                TextColumn::make('customer.name')
                    ->label(__('app.fields.customer'))
                    ->searchable(),
                TextColumn::make('document_type')
                    ->label(__('app.fields.document_type_sale'))
                    ->badge(),
                TextColumn::make('status')
                    ->label(__('app.fields.status'))
                    ->badge(),
                TextColumn::make('total')
                    ->label(__('app.fields.total'))
                    ->money('COP')
                    ->sortable(),
                TextColumn::make('sold_at')
                    ->label(__('app.fields.sold_at'))
                    ->date()
                    ->sortable(),
            ]);
    }
}
