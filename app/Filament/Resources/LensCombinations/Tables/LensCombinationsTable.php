<?php

namespace App\Filament\Resources\LensCombinations\Tables;

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

class LensCombinationsTable
{
    public static function configure(Table $table): Table
    {
        return $table
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
                TextColumn::make('prices_min_price')->min('prices', 'price')->label(__('app.fields.price_from'))->money('COP')->sortable(),
                TextColumn::make('installation_price')->label(__('app.fields.installation_price'))->money('COP')->sortable(),
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
