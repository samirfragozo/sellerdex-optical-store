<?php

namespace App\Filament\Resources\Taxes\Schemas;

use App\Enums\TaxTreatment;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

class TaxForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label(__('app.fields.name'))
                    ->required()
                    ->maxLength(255),
                TextInput::make('rate')
                    ->label(__('app.fields.rate'))
                    ->required()
                    ->numeric()
                    ->minValue(fn (Get $get): float => self::treatment($get) === TaxTreatment::Taxed ? 0.01 : 0)
                    ->maxValue(100)
                    ->default(0)
                    ->suffix('%')
                    // Exempt and excluded taxes are always 0 %: the field is locked but still saved.
                    ->disabled(fn (Get $get): bool => self::isZeroRated($get))
                    ->dehydrated()
                    ->dehydrateStateUsing(fn (mixed $state, Get $get): mixed => self::isZeroRated($get) ? 0 : $state),
                Select::make('treatment')
                    ->label(__('app.fields.treatment'))
                    ->options(TaxTreatment::options())
                    ->live()
                    ->required(),
                TextInput::make('dian_code')
                    ->label(__('app.fields.dian_code'))
                    ->maxLength(4),
                Section::make(__('app.sections.options'))
                    ->columns(2)
                    ->schema([
                        Toggle::make('is_active')
                            ->label(__('app.fields.active'))
                            ->default(true),
                        Toggle::make('is_default')
                            ->label(__('app.fields.is_default')),
                    ]),
            ]);
    }

    private static function treatment(Get $get): ?TaxTreatment
    {
        $state = $get('treatment');

        return $state instanceof TaxTreatment ? $state : TaxTreatment::tryFrom((string) $state);
    }

    private static function isZeroRated(Get $get): bool
    {
        return in_array(self::treatment($get), [TaxTreatment::Exempt, TaxTreatment::Excluded], true);
    }
}
