<?php

namespace App\Filament\Resources\Expenses\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class ExpenseForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('expense_category_id')
                    ->label(__('app.fields.category'))
                    ->relationship('category', 'name')
                    ->required(),
                TextInput::make('description')
                    ->label(__('app.fields.description'))
                    ->required()
                    ->maxLength(255),
                TextInput::make('amount')
                    ->label(__('app.fields.amount'))
                    ->required()
                    ->numeric()
                    ->minValue(0),
                Select::make('payment_method_id')
                    ->label(__('app.fields.payment_method'))
                    ->relationship('paymentMethod', 'name')
                    ->nullable(),
                DatePicker::make('spent_at')
                    ->label(__('app.fields.date'))
                    ->required(),
                Textarea::make('notes')
                    ->label(__('app.fields.notes'))
                    ->maxLength(1000)
                    ->columnSpanFull(),
            ]);
    }
}
