<?php

namespace App\Filament\Resources\Sales\Tables;

use App\Enums\SaleDocumentType;
use App\Enums\SaleStatus;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

class SalesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('number')
                    ->label(__('app.fields.number'))
                    ->searchable(),
                TextColumn::make('customer.name')
                    ->label(__('app.fields.customer'))
                    ->searchable(),
                TextColumn::make('document_type')
                    ->label(__('app.fields.document_type_sale'))
                    ->badge()
                    ->formatStateUsing(fn (SaleDocumentType $state) => $state->label()),
                TextColumn::make('status')
                    ->label(__('app.fields.status'))
                    ->badge()
                    ->formatStateUsing(fn (SaleStatus $state) => $state->label()),
                TextColumn::make('total')
                    ->label(__('app.fields.total'))
                    ->money('COP')
                    ->sortable(),
                TextColumn::make('balance')
                    ->label(__('app.fields.balance'))
                    ->money('COP'),
                TextColumn::make('sold_at')
                    ->label(__('app.fields.sold_at'))
                    ->date()
                    ->sortable(),
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
