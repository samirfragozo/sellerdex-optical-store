<?php

namespace App\Filament\Resources\LensCombinations\Schemas;

use App\Models\LensMaterial;
use App\Models\LensTechnology;
use App\Models\LensType;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class LensCombinationForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('lens_type_id')
                ->label(__('app.resources.lens_type.label'))
                ->options(fn () => LensType::query()->where('is_active', true)->pluck('name', 'id'))
                ->required()
                ->searchable(),
            Select::make('lens_technology_id')
                ->label(__('app.resources.lens_technology.label'))
                ->options(fn () => LensTechnology::query()->where('is_active', true)->pluck('name', 'id'))
                ->required()
                ->searchable(),
            Select::make('lens_material_id')
                ->label(__('app.resources.lens_material.label'))
                ->options(fn () => LensMaterial::query()->where('is_active', true)->pluck('name', 'id'))
                ->required()
                ->searchable(),
            TextInput::make('cost')
                ->label(__('app.fields.cost'))
                ->required()
                ->numeric()
                ->prefix('$'),
            TextInput::make('price')
                ->label(__('app.fields.price'))
                ->required()
                ->numeric()
                ->prefix('$'),
            TextInput::make('installation_price')
                ->label(__('app.fields.installation_price'))
                ->required()
                ->numeric()
                ->prefix('$'),
            Toggle::make('is_active')
                ->label(__('app.fields.active_f'))
                ->required()
                ->default(true),
        ]);
    }
}
