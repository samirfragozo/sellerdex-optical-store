<?php

namespace App\Filament\Resources\ProductCategories\Schemas;

use App\Models\ProductCategory;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ProductCategoryForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label(__('app.fields.name'))
                    ->required(),
                TextInput::make('key')
                    ->label(__('app.fields.category_key'))
                    ->disabled(fn (?ProductCategory $record): bool => (bool) $record?->is_system)
                    ->required(),
                Section::make(__('app.sections.options'))
                    ->schema([
                        Toggle::make('is_active')
                            ->label(__('app.fields.active_f'))
                            ->required(),
                        Toggle::make('requires_prescription')
                            ->label(__('app.fields.requires_prescription')),
                        Toggle::make('generates_lab_order')
                            ->label(__('app.fields.generates_lab_order')),
                        Toggle::make('is_made_to_order')
                            ->label(__('app.fields.is_made_to_order')),
                    ]),
            ]);
    }
}
