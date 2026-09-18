<?php

namespace App\Filament\Resources\LensCombinations\Schemas;

use App\Models\LensMaterial;
use App\Models\LensTechnology;
use App\Models\LensType;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Unique;

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
                ->searchable()
                ->unique(
                    modifyRuleUsing: fn (Unique $rule, Get $get): Unique => $rule
                        ->where('company_id', Auth::user()?->company_id)
                        ->where('lens_type_id', $get('lens_type_id'))
                        ->where('lens_technology_id', $get('lens_technology_id')),
                    ignoreRecord: true,
                )
                ->validationMessages(['unique' => __('app.resources.lens_combination.duplicate')]),
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
