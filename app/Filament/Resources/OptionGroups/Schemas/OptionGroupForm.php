<?php

namespace App\Filament\Resources\OptionGroups\Schemas;

use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class OptionGroupForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label(__('app.fields.name'))
                    ->required(),
                Toggle::make('is_required')
                    ->label(__('app.fields.is_required'))
                    ->default(true)
                    ->required(),
                Toggle::make('is_active')
                    ->label(__('app.fields.active'))
                    ->default(true)
                    ->required(),
                Repeater::make('options')
                    ->relationship()
                    ->label(__('app.fields.options'))
                    ->schema([
                        TextInput::make('name')
                            ->label(__('app.fields.name'))
                            ->required(),
                        TextInput::make('price')
                            ->label(__('app.fields.price'))
                            ->numeric()
                            ->default(0)
                            ->required()
                            ->prefix('$'),
                        TextInput::make('cost')
                            ->label(__('app.fields.cost'))
                            ->numeric()
                            ->default(0)
                            ->required()
                            ->prefix('$'),
                        TextInput::make('sort_order')
                            ->label(__('app.fields.sort_order'))
                            ->numeric(),
                    ])
                    ->columns(4)
                    ->columnSpanFull()
                    ->reorderable('sort_order')
                    ->defaultItems(1),
            ]);
    }
}
