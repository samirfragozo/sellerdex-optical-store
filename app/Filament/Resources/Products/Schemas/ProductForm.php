<?php

namespace App\Filament\Resources\Products\Schemas;

use App\Models\Product;
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
                    ->required(),
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
                TextInput::make('tax_rate')
                    ->label(__('app.fields.tax_rate'))
                    ->numeric()
                    ->minValue(0)
                    ->maxValue(100)
                    ->default(0)
                    ->suffix('%'),
                TextInput::make('stock')
                    ->label(__('app.fields.stock'))
                    ->numeric()
                    ->minValue(0),
                Textarea::make('specs')
                    ->label(__('app.fields.specs'))
                    ->columnSpanFull(),
                Section::make(__('app.sections.options'))
                    ->columns(2)
                    ->schema([
                        Toggle::make('is_stockable')
                            ->label(__('app.fields.is_stockable'))
                            ->required(),
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
