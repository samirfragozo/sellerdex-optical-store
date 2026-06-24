<?php

namespace App\Filament\Resources\ProductCategories\RelationManagers;

use App\Filament\Resources\Products\ProductResource;
use App\Models\Product;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ProductsRelationManager extends RelationManager
{
    protected static string $relationship = 'products';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label(__('app.fields.name'))
                    ->required()
                    ->maxLength(255),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->columns([
                TextColumn::make('name')
                    ->label(__('app.fields.name'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('sku')
                    ->label(__('app.fields.sku'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('price')
                    ->label(__('app.fields.price'))
                    ->money('COP')
                    ->sortable(),
                TextColumn::make('cost')
                    ->label(__('app.fields.cost'))
                    ->money('COP')
                    ->sortable()
                    ->visible(fn () => auth()->user()?->isAdmin() === true),
                TextColumn::make('stock')
                    ->label(__('app.fields.stock'))
                    ->numeric()
                    ->sortable(),
                IconColumn::make('is_active')
                    ->label(__('app.fields.active'))
                    ->boolean(),
            ])
            ->recordActions([
                EditAction::make()
                    ->url(fn (Product $record): string => ProductResource::getUrl('edit', ['record' => $record])),
            ]);
    }
}
