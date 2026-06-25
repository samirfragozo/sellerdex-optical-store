<?php

namespace App\Filament\Resources\PaymentMethods\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class ExpensesRelationManager extends RelationManager
{
    protected static string $relationship = 'expenses';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('app.relations.expenses');
    }

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('spent_at', 'desc')
            ->columns([
                TextColumn::make('description')
                    ->label(__('app.fields.description'))
                    ->searchable(),
                TextColumn::make('category.name')
                    ->label(__('app.fields.category'))
                    ->searchable(),
                TextColumn::make('amount')
                    ->label(__('app.fields.amount'))
                    ->money('COP')
                    ->sortable(),
                TextColumn::make('spent_at')
                    ->label(__('app.fields.date'))
                    ->date()
                    ->sortable(),
            ]);
    }
}
