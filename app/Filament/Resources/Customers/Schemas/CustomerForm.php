<?php

namespace App\Filament\Resources\Customers\Schemas;

use App\Enums\DocumentType;
use App\Enums\FiscalResponsibility;
use App\Enums\PersonType;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
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
                Section::make(__('app.fields.fiscal_section'))
                    ->description(__('app.fields.fiscal_section_help'))
                    ->columns(2)
                    ->collapsed()
                    ->columnSpanFull()
                    ->schema([
                        Select::make('person_type')->label(__('app.fields.person_type'))->options(PersonType::options()),
                        TextInput::make('dane_municipality_code')
                            ->label(__('app.fields.dane_municipality_code'))
                            ->helperText(__('app.fields.dane_municipality_code_help'))
                            ->regex('/^\d{5}$/')
                            ->maxLength(5),
                        CheckboxList::make('fiscal_responsibilities')
                            ->label(__('app.fields.fiscal_responsibilities'))
                            ->options(FiscalResponsibility::options())
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
