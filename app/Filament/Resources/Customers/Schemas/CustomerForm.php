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
                    ->required()
                    ->maxLength(255),
                TextInput::make('last_name')
                    ->label(__('app.fields.last_name'))
                    ->maxLength(255),
                Select::make('document_type')
                    ->label(__('app.fields.document_type'))
                    ->options(DocumentType::options())
                    ->default(DocumentType::CC->value)
                    ->required(),
                TextInput::make('id_number')
                    ->label(__('app.fields.id_number'))
                    ->required()
                    ->maxLength(255)
                    ->unique(ignoreRecord: true),
                TextInput::make('phone')
                    ->label(__('app.fields.phone'))
                    ->tel()
                    ->required()
                    ->maxLength(255),
                TextInput::make('address')
                    ->label(__('app.fields.address'))
                    ->maxLength(255),
                TextInput::make('city')
                    ->label(__('app.fields.city'))
                    ->maxLength(255),
                DatePicker::make('birth_date')
                    ->label(__('app.fields.birth_date'))
                    ->maxDate(now()),
                TextInput::make('email')
                    ->label(__('app.fields.email'))
                    ->email()
                    ->maxLength(255),
                Textarea::make('notes')
                    ->label(__('app.fields.notes'))
                    ->maxLength(1000)
                    ->columnSpanFull(),
            ]);
    }
}
