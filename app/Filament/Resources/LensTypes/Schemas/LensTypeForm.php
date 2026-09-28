<?php

namespace App\Filament\Resources\LensTypes\Schemas;

use App\Enums\LensKind;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class LensTypeForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')
                ->label(__('app.fields.name'))
                ->required()
                ->maxLength(255),
            Select::make('kind')
                ->label(__('app.fields.kind'))
                ->options(LensKind::options())
                ->default(LensKind::SingleVision->value)
                ->required(),
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
