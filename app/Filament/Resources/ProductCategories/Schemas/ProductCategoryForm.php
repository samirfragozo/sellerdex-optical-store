<?php

namespace App\Filament\Resources\ProductCategories\Schemas;

use App\Enums\VatRegime;
use App\Models\Company;
use App\Models\ProductCategory;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ProductCategoryForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label(__('app.fields.name'))
                    ->required()
                    ->maxLength(255),
                TextInput::make('key')
                    ->label(__('app.fields.category_key'))
                    ->disabled(fn (?ProductCategory $record): bool => (bool) $record?->is_system)
                    ->required()
                    ->maxLength(255),
                TextInput::make('warranty_months')
                    ->label(__('app.fields.warranty_months'))
                    ->helperText(__('app.fields.warranty_months_help'))
                    ->integer()
                    ->minValue(1)
                    ->maxValue(120)
                    ->default(12)
                    ->required()
                    ->suffix(__('app.fields.months')),
                Select::make('default_tax_id')
                    ->label(__('app.taxes.default_for_category'))
                    ->relationship('defaultTax', 'name', fn ($query, $record) => $query->where(fn ($q) => $q->where('is_active', true)->when($record?->default_tax_id, fn ($q, $id) => $q->orWhere('taxes.id', $id))))
                    ->placeholder(__('app.taxes.none'))
                    ->visible(fn (): bool => Company::current()->vat_regime === VatRegime::Responsible),
                Section::make(__('app.sections.options'))
                    ->schema([
                        Toggle::make('is_active')
                            ->label(__('app.fields.active_f'))
                            ->required(),
                        Toggle::make('requires_prescription')
                            ->label(__('app.fields.requires_prescription')),
                        Toggle::make('generates_lab_order')
                            ->label(__('app.fields.generates_lab_order')),
                        Toggle::make('is_made_to_order')
                            ->label(__('app.fields.is_made_to_order')),
                    ]),
            ]);
    }
}
