<?php

namespace App\Filament\Resources\LensCombinations\Tables;

use App\Models\LensCombination;
use App\Models\LensMaterial;
use App\Models\LensTechnology;
use App\Models\LensType;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class LensCombinationsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->withExists([
                'priceBands as has_sale_price' => fn (Builder $band) => $band->where('price', '>', 0),
                'cells as has_lab_cost' => fn (Builder $cell) => $cell->where('is_active', true)
                    ->whereHas('sheet.supplier', fn (Builder $lab) => $lab->where('is_laboratory', true)->where('is_active', true)),
            ]))
            ->columns([
                TextColumn::make('lensType.name')
                    ->label(__('app.resources.lens_type.label'))
                    ->searchable(),
                TextColumn::make('lensTechnology.name')
                    ->label(__('app.resources.lens_technology.label'))
                    ->searchable(),
                TextColumn::make('lensMaterial.name')
                    ->label(__('app.resources.lens_material.label'))
                    ->searchable(),
                TextColumn::make('price_bands_min_price')->min('priceBands', 'price')->label(__('app.fields.price_from'))->money('COP')->sortable(),
                TextColumn::make('installation_price')->label(__('app.fields.installation_price'))->money('COP')->sortable(),
                IconColumn::make('is_sellable')->label(__('app.fields.sellable'))->boolean()
                    ->state(fn (LensCombination $record): bool => $record->is_active && $record->has_sale_price && $record->has_lab_cost),
                IconColumn::make('is_active')->label(__('app.fields.active_f'))->boolean(),
            ])
            ->filters([
                SelectFilter::make('lens_type_id')
                    ->label(__('app.resources.lens_type.label'))
                    ->options(fn () => LensType::query()->pluck('name', 'id')),
                SelectFilter::make('lens_technology_id')
                    ->label(__('app.resources.lens_technology.label'))
                    ->options(fn () => LensTechnology::query()->pluck('name', 'id')),
                SelectFilter::make('lens_material_id')
                    ->label(__('app.resources.lens_material.label'))
                    ->options(fn () => LensMaterial::query()->pluck('name', 'id')),
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
