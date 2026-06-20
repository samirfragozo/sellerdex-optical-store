<?php

namespace App\Filament\Resources\Prescriptions\Schemas;

use App\Enums\LensType;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class PrescriptionForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('customer_id')
                    ->label(__('app.fields.customer'))
                    ->relationship('customer', 'name')
                    ->required(),
                DatePicker::make('exam_date')
                    ->label(__('app.fields.exam_date'))
                    ->required(),
                Select::make('lens_type')
                    ->label(__('app.fields.lens_type'))
                    ->options(LensType::options()),
                Select::make('filters')
                    ->label(__('app.fields.filters'))
                    ->multiple()
                    ->options([
                        'Fotocromático' => 'Fotocromático',
                        'Antirreflejo Blue' => 'Antirreflejo Blue',
                        'FotoBlue' => 'FotoBlue',
                    ]),
                TextInput::make('usage')
                    ->label(__('app.fields.usage')),
                TextInput::make('control_period')
                    ->label(__('app.fields.control_period')),
                Textarea::make('diagnosis')
                    ->label(__('app.fields.diagnosis'))
                    ->columnSpanFull(),
                TextInput::make('drops')
                    ->label(__('app.fields.drops')),
                TextInput::make('lensometry')
                    ->label(__('app.fields.lensometry')),
                Section::make(__('app.sections.right_eye'))
                    ->columns(2)
                    ->schema([
                        TextInput::make('od_sphere')
                            ->label(__('app.fields.sphere')),
                        TextInput::make('od_cylinder')
                            ->label(__('app.fields.cylinder')),
                        TextInput::make('od_axis')
                            ->label(__('app.fields.axis')),
                        TextInput::make('od_add')
                            ->label(__('app.fields.add')),
                        TextInput::make('od_va')
                            ->label(__('app.fields.va')),
                        TextInput::make('od_pd')
                            ->label(__('app.fields.pd')),
                    ]),
                Section::make(__('app.sections.left_eye'))
                    ->columns(2)
                    ->schema([
                        TextInput::make('os_sphere')
                            ->label(__('app.fields.sphere')),
                        TextInput::make('os_cylinder')
                            ->label(__('app.fields.cylinder')),
                        TextInput::make('os_axis')
                            ->label(__('app.fields.axis')),
                        TextInput::make('os_add')
                            ->label(__('app.fields.add')),
                        TextInput::make('os_va')
                            ->label(__('app.fields.va')),
                        TextInput::make('os_pd')
                            ->label(__('app.fields.pd')),
                    ]),
            ]);
    }
}
