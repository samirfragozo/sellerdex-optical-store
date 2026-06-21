<?php

namespace App\Filament\Resources\CashCloses\Schemas;

use App\Enums\CashCloseType;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class CashCloseForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('type')
                    ->label(__('app.fields.type'))
                    ->options(CashCloseType::options())
                    ->default(CashCloseType::Daily->value)
                    ->required(),
                DatePicker::make('period_start')
                    ->label(__('app.fields.period_start'))
                    ->default(now())
                    ->required(),
                DatePicker::make('period_end')
                    ->label(__('app.fields.period_end'))
                    ->default(now())
                    ->required(),
                TextInput::make('opening_cash')
                    ->label(__('app.fields.opening_cash'))
                    ->numeric()
                    ->default(0)
                    ->prefix('$'),
                TextInput::make('total_sales')
                    ->label(__('app.fields.total_sales'))
                    ->numeric()
                    ->prefix('$')
                    ->disabled()
                    ->dehydrated(),
                TextInput::make('total_collected')
                    ->label(__('app.fields.total_collected'))
                    ->numeric()
                    ->prefix('$')
                    ->disabled()
                    ->dehydrated(),
                TextInput::make('total_expenses')
                    ->label(__('app.fields.total_expenses'))
                    ->numeric()
                    ->prefix('$')
                    ->disabled()
                    ->dehydrated(),
                TextInput::make('total_receivable')
                    ->label(__('app.fields.total_receivable'))
                    ->numeric()
                    ->prefix('$')
                    ->disabled()
                    ->dehydrated(),
                TextInput::make('expected_cash')
                    ->label(__('app.fields.expected_cash'))
                    ->numeric()
                    ->prefix('$')
                    ->disabled()
                    ->dehydrated(),
                TextInput::make('counted_cash')
                    ->label(__('app.fields.counted_cash'))
                    ->numeric()
                    ->default(0)
                    ->prefix('$'),
                TextInput::make('difference')
                    ->label(__('app.fields.difference'))
                    ->numeric()
                    ->prefix('$')
                    ->disabled()
                    ->dehydrated(false),
                Textarea::make('notes')
                    ->label(__('app.fields.notes'))
                    ->columnSpanFull(),
            ]);
    }
}
