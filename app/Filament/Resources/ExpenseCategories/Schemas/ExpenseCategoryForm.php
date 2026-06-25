<?php

namespace App\Filament\Resources\ExpenseCategories\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ExpenseCategoryForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label(__('app.fields.name'))
                    ->required(),
                Section::make(__('app.sections.options'))
                    ->schema([
                        Toggle::make('is_active')
                            ->label(__('app.fields.active_f'))
                            ->required(),
                    ]),
            ]);
    }
}
