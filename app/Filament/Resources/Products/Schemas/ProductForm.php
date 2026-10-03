<?php

namespace App\Filament\Resources\Products\Schemas;

use App\Enums\VatRegime;
use App\Models\Company;
use App\Models\Product;
use App\Models\ProductCategory;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;

class ProductForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label(__('app.fields.name'))
                    ->required()
                    ->maxLength(255),
                TextInput::make('sku')
                    ->label(__('app.fields.sku'))
                    ->maxLength(255),
                Select::make('product_category_id')
                    ->label(__('app.fields.category'))
                    ->relationship('category', 'name')
                    ->required()
                    ->live()
                    ->afterStateUpdated(function ($state, $set, $get): void {
                        $tax = ProductCategory::find($state)?->defaultTax;
                        if (! $get('tax_id') && $tax?->is_active) {
                            $set('tax_id', $tax->id);
                        }
                    }),
                Select::make('base_product_id')
                    ->label(__('app.fields.base_product'))
                    ->relationship(
                        name: 'baseProduct',
                        titleAttribute: 'name',
                        modifyQueryUsing: fn (Builder $query, ?Product $record) => $query
                            ->whereNull('base_product_id')
                            ->when($record, fn (Builder $q) => $q->whereKeyNot($record->id)),
                    )
                    ->searchable(),
                TextInput::make('brand')
                    ->label(__('app.fields.brand'))
                    ->maxLength(255),
                TextInput::make('price')
                    ->label(__('app.fields.price'))
                    ->required()
                    ->numeric()
                    ->minValue(0)
                    ->default(0)
                    ->prefix('$'),
                TextInput::make('cost')
                    ->label(__('app.fields.cost'))
                    ->required()
                    ->numeric()
                    ->minValue(0)
                    ->default(0)
                    ->prefix('$')
                    ->visible(fn () => auth()->user()?->isAdmin() === true),
                Select::make('tax_id')
                    ->label(__('app.fields.tax'))
                    ->relationship('tax', 'name', fn ($query, $record) => $query->where(fn ($q) => $q->where('is_active', true)->when($record?->tax_id, fn ($q, $id) => $q->orWhere('taxes.id', $id))))
                    ->placeholder(__('app.taxes.none'))
                    ->helperText(__('app.taxes.price_includes_tax'))
                    ->visible(fn (): bool => Company::current()->vat_regime === VatRegime::Responsible),
                TextInput::make('stock')
                    ->label(fn (string $operation): string => $operation === 'create' ? __('app.inventory.initial_stock') : __('app.fields.stock'))
                    ->numeric()
                    ->minValue(0)
                    ->default(0)
                    ->disabled(fn (string $operation): bool => $operation === 'edit')
                    ->dehydrated(false)
                    ->visible(fn (): bool => Company::current()->tracksInventory()),
                Textarea::make('specs')
                    ->label(__('app.fields.specs'))
                    ->columnSpanFull(),
                Section::make(__('app.sections.options'))
                    ->columns(2)
                    ->schema([
                        Toggle::make('is_stockable')
                            ->label(__('app.fields.is_stockable'))
                            ->required()
                            ->visible(fn (): bool => Company::current()->tracksInventory()),
                        Toggle::make('is_active')
                            ->label(__('app.fields.active'))
                            ->required(),
                        Toggle::make('is_pos_selectable')
                            ->label(__('app.fields.is_pos_selectable'))
                            ->default(true)
                            ->required(),
                        CheckboxList::make('optionGroups')
                            ->label(__('app.fields.option_groups'))
                            ->relationship('optionGroups', 'name')
                            ->columnSpanFull(),
                        CheckboxList::make('variantOptions')
                            ->label(__('app.fields.variant_options'))
                            ->relationship('variantOptions', 'name')
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
