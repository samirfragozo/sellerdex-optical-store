<?php

namespace App\Filament\Resources\Customers\Schemas;

use App\Enums\DocumentType;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class CustomerForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label(__('app.fields.first_name'))
                    ->required(),
                TextInput::make('last_name')
                    ->label(__('app.fields.last_name')),
                Select::make('document_type')
                    ->label(__('app.fields.document_type'))
                    ->options(DocumentType::options())
                    ->default(DocumentType::CC->value)
                    ->required(),
                TextInput::make('id_number')
                    ->label(__('app.fields.id_number')),
                TextInput::make('phone')
                    ->label(__('app.fields.phone'))
                    ->tel(),
                TextInput::make('address')
                    ->label(__('app.fields.address')),
                TextInput::make('city')
                    ->label(__('app.fields.city')),
                DatePicker::make('birth_date')
                    ->label(__('app.fields.birth_date')),
                TextInput::make('email')
                    ->label(__('app.fields.email'))
                    ->email(),
                Textarea::make('notes')
                    ->label(__('app.fields.notes'))
                    ->columnSpanFull(),
            ]);
    }
}
