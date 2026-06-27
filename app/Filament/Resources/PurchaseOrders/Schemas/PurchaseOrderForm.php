<?php

namespace App\Filament\Resources\PurchaseOrders\Schemas;

use App\Enums\PurchaseOrderStatus;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class PurchaseOrderForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('number')
                    ->label(__('app.fields.number'))
                    ->maxLength(255),
                Select::make('supplier_id')
                    ->label(__('app.fields.supplier'))
                    ->relationship('supplier', 'name')
                    ->searchable()
                    ->required(),
                Select::make('status')
                    ->label(__('app.fields.status'))
                    ->options(PurchaseOrderStatus::options())
                    ->default(PurchaseOrderStatus::Draft->value)
                    ->required(),
                DatePicker::make('ordered_at')
                    ->label(__('app.fields.ordered_at')),
                Repeater::make('items')
                    ->label(__('app.relations.products'))
                    ->relationship('items')
                    ->schema([
                        Select::make('product_id')
                            ->label(__('app.fields.product'))
                            ->relationship('product', 'name')
                            ->searchable()
                            ->required(),
                        TextInput::make('quantity')
                            ->label(__('app.fields.quantity'))
                            ->numeric()
                            ->minValue(1)
                            ->default(1)
                            ->required(),
                        TextInput::make('unit_cost')
                            ->label(__('app.fields.unit_cost'))
                            ->numeric()
                            ->minValue(0)
                            ->default(0)
                            ->required(),
                    ])
                    ->columns(3)
                    ->columnSpanFull(),
                Textarea::make('notes')
                    ->label(__('app.fields.notes'))
                    ->columnSpanFull(),
            ]);
    }
}
