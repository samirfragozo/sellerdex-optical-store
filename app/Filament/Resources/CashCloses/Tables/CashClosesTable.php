<?php

namespace App\Filament\Resources\CashCloses\Tables;

use App\Enums\CashCloseStatus;
use App\Enums\CashCloseType;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class CashClosesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('type')
                    ->label(__('app.fields.type'))
                    ->badge()
                    ->formatStateUsing(fn (CashCloseType $state) => $state->label()),
                TextColumn::make('period_start')
                    ->label(__('app.fields.period_start'))
                    ->date()
                    ->sortable(),
                TextColumn::make('period_end')
                    ->label(__('app.fields.period_end'))
                    ->date()
                    ->sortable(),
                TextColumn::make('total_sales')
                    ->label(__('app.fields.total_sales'))
                    ->money('COP')
                    ->sortable(),
                TextColumn::make('total_collected')
                    ->label(__('app.fields.total_collected'))
                    ->money('COP')
                    ->sortable(),
                TextColumn::make('total_expenses')
                    ->label(__('app.fields.total_expenses'))
                    ->money('COP')
                    ->sortable(),
                TextColumn::make('expected_cash')
                    ->label(__('app.fields.expected_cash'))
                    ->money('COP')
                    ->sortable(),
                TextColumn::make('counted_cash')
                    ->label(__('app.fields.counted_cash'))
                    ->money('COP')
                    ->sortable(),
                TextColumn::make('difference')
                    ->label(__('app.fields.difference'))
                    ->money('COP')
                    ->sortable(),
                TextColumn::make('status')
                    ->label(__('app.fields.status'))
                    ->badge()
                    ->formatStateUsing(fn (CashCloseStatus $state) => $state->label()),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('period_start', 'desc')
            ->filters([
                //
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
