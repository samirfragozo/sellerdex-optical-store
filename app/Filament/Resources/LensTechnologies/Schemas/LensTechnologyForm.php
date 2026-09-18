<?php

namespace App\Filament\Resources\LensTechnologies\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class LensTechnologyForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')
                ->label(__('app.fields.name'))
                ->required()
                ->maxLength(255),
            TextInput::make('sort_order')
                ->label(__('app.fields.sort_order'))
                ->numeric(),
            Toggle::make('is_active')
                ->label(__('app.fields.active_f'))
                ->required()
                ->default(true),
        ]);
    }
}
