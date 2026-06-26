<?php

namespace App\Filament\Resources\Suppliers\Schemas;

use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class SupplierForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label(__('app.fields.name'))
                    ->required()
                    ->maxLength(255),
                TextInput::make('nit')
                    ->label(__('app.fields.nit'))
                    ->maxLength(255),
                TextInput::make('contact_name')
                    ->label(__('app.fields.contact_name'))
                    ->maxLength(255),
                TextInput::make('phone')
                    ->label(__('app.fields.phone'))
                    ->maxLength(255),
                TextInput::make('email')
                    ->label(__('app.fields.email'))
                    ->email()
                    ->maxLength(255),
                TextInput::make('address')
                    ->label(__('app.fields.address'))
                    ->maxLength(255),
                Toggle::make('is_laboratory')
                    ->label(__('app.fields.is_laboratory')),
                Toggle::make('is_active')
                    ->label(__('app.fields.active'))
                    ->default(true),
                Textarea::make('notes')
                    ->label(__('app.fields.notes'))
                    ->columnSpanFull(),
            ]);
    }
}
