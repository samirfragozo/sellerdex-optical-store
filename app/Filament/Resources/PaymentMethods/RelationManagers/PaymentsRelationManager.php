<?php

namespace App\Filament\Resources\PaymentMethods\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class PaymentsRelationManager extends RelationManager
{
    protected static string $relationship = 'payments';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('app.relations.payments');
    }

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('paid_at', 'desc')
            ->columns([
                TextColumn::make('sale.number')
                    ->label(__('app.fields.number'))
                    ->searchable(),
                TextColumn::make('amount')
                    ->label(__('app.fields.amount'))
                    ->money('COP')
                    ->sortable(),
                TextColumn::make('paid_at')
                    ->label(__('app.fields.paid_at'))
                    ->date()
                    ->sortable(),
                TextColumn::make('reference')
                    ->label(__('app.fields.reference'))
                    ->searchable(),
            ]);
    }
}
