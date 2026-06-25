<?php

namespace App\Filament\Resources\Products\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ProductForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label(__('app.fields.name'))
                    ->required(),
                TextInput::make('sku')
                    ->label(__('app.fields.sku')),
                Select::make('product_category_id')
                    ->label(__('app.fields.category'))
                    ->relationship('category', 'name')
                    ->required(),
                TextInput::make('brand')
                    ->label(__('app.fields.brand')),
                TextInput::make('price')
                    ->label(__('app.fields.price'))
                    ->required()
                    ->numeric()
                    ->default(0)
                    ->prefix('$'),
                TextInput::make('cost')
                    ->label(__('app.fields.cost'))
                    ->required()
                    ->numeric()
                    ->default(0)
                    ->prefix('$')
                    ->visible(fn () => auth()->user()?->isAdmin() === true),
                TextInput::make('stock')
                    ->label(__('app.fields.stock'))
                    ->numeric(),
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
                    ]),
            ]);
    }
}
