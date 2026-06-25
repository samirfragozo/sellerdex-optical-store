<?php

namespace App\Filament\Resources\PaymentMethods\Schemas;

use App\Models\PaymentMethod;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class PaymentMethodForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label(__('app.fields.name'))
                    ->required(),
                TextInput::make('sort_order')
                    ->label(__('app.fields.sort_order'))
                    ->required()
                    ->numeric()
                    ->default(0),
                Section::make(__('app.sections.options'))
                    ->columns(2)
                    ->schema([
                        Toggle::make('is_default')
                            ->label(__('app.fields.is_default'))
                            ->disabled()
                            ->dehydrated(false),
                        Toggle::make('is_active')
                            ->label(__('app.fields.active'))
                            ->disabled(fn (?PaymentMethod $record) => $record?->isProtected() === true),
                    ]),
            ]);
    }
}
