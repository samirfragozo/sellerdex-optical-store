<?php

namespace App\Filament\Resources\Sales\Schemas;

use App\Enums\SaleDocumentType;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class SaleForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('customer_id')
                    ->label(__('app.fields.customer'))
                    ->relationship('customer', 'name')
                    ->searchable()
                    ->preload()
                    ->required(),
                Select::make('document_type')
                    ->label(__('app.fields.document_type_sale'))
                    ->options(SaleDocumentType::options())
                    ->default(SaleDocumentType::Order->value)
                    ->required(),
                Select::make('prescription_id')
                    ->label(__('app.fields.prescription'))
                    ->relationship('prescription', 'id')
                    ->searchable(),
                DatePicker::make('sold_at')
                    ->label(__('app.fields.sold_at'))
                    ->default(now())
                    ->required(),
                TextInput::make('discount')
                    ->label(__('app.fields.discount'))
                    ->numeric()
                    ->default(0)
                    ->prefix('$'),
                Textarea::make('notes')
                    ->label(__('app.fields.notes'))
                    ->columnSpanFull(),
                Repeater::make('items')
                    ->label(__('app.fields.items'))
                    ->relationship()
                    ->schema([
                        Select::make('product_id')
                            ->label(__('app.fields.product'))
                            ->relationship('product', 'name')
                            ->searchable(),
                        TextInput::make('description')
                            ->label(__('app.fields.description'))
                            ->required(),
                        TextInput::make('quantity')
                            ->label(__('app.fields.quantity'))
                            ->numeric()
                            ->default(1)
                            ->required(),
                        TextInput::make('unit_price')
                            ->label(__('app.fields.unit_price'))
                            ->numeric()
                            ->default(0)
                            ->prefix('$')
                            ->required(),
                        TextInput::make('unit_cost')
                            ->label(__('app.fields.unit_cost'))
                            ->numeric()
                            ->default(0)
                            ->prefix('$')
                            ->visible(fn () => auth()->user()?->isAdmin() === true),
                    ])
                    ->columnSpanFull()
                    ->defaultItems(1),
            ]);
    }
}
