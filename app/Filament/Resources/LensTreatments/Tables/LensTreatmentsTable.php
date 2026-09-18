<?php

namespace App\Filament\Resources\LensTreatments\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class LensTreatmentsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('sort_order')
            ->columns([
                TextColumn::make('name')
                    ->label(__('app.fields.name'))
                    ->searchable(),
                TextColumn::make('sort_order')
                    ->label(__('app.fields.sort_order'))
                    ->sortable(),
                TextColumn::make('price')
                    ->label(__('app.fields.price'))
                    ->money('COP')
                    ->sortable(),
                TextColumn::make('cost')
                    ->label(__('app.fields.cost'))
                    ->money('COP')
                    ->sortable(),
                IconColumn::make('is_active')
                    ->label(__('app.fields.active_f'))
                    ->boolean(),
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
