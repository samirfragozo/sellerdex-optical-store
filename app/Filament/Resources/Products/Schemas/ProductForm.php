<?php

namespace App\Filament\Resources\Products\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
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
                Toggle::make('is_stockable')
                    ->label(__('app.fields.is_stockable'))
                    ->required(),
                TextInput::make('stock')
                    ->label(__('app.fields.stock'))
                    ->numeric(),
                Toggle::make('is_active')
                    ->label(__('app.fields.active'))
                    ->required(),
                Textarea::make('specs')
                    ->label(__('app.fields.specs'))
                    ->columnSpanFull(),
            ]);
    }
}
