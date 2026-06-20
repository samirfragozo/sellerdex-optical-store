<?php

namespace App\Filament\Resources\Products\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

class ProductsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label(__('app.fields.name'))
                    ->searchable(),
                TextColumn::make('sku')
                    ->label(__('app.fields.sku'))
                    ->searchable(),
                TextColumn::make('category.name')
                    ->label(__('app.fields.category'))
                    ->searchable(),
                TextColumn::make('brand')
                    ->label(__('app.fields.brand'))
                    ->searchable(),
                TextColumn::make('price')
                    ->label(__('app.fields.price'))
                    ->money('COP')
                    ->sortable(),
                TextColumn::make('cost')
                    ->label(__('app.fields.cost'))
                    ->money('COP')
                    ->sortable()
                    ->visible(fn () => auth()->user()?->isAdmin() === true),
                TextColumn::make('stock')
                    ->label(__('app.fields.stock'))
                    ->numeric()
                    ->sortable(),
                IconColumn::make('is_active')
                    ->label(__('app.fields.active'))
                    ->boolean(),
                TextColumn::make('deleted_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                TrashedFilter::make(),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]),
            ]);
    }
}
