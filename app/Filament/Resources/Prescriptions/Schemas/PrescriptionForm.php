<?php

namespace App\Filament\Resources\Prescriptions\Schemas;

use App\Enums\LensType;
use App\Rules\Diopter;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ToggleButtons;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\FusedGroup;
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
                    ->searchable()
                    ->required(),
                DatePicker::make('exam_date')
                    ->label(__('app.fields.exam_date'))
                    ->required()
                    ->maxDate(now())
                    ->minDate(now()->subYears(2)),
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
                Textarea::make('diagnosis')
                    ->label(__('app.fields.diagnosis'))
                    ->maxLength(1000)
                    ->columnSpanFull(),
                Section::make(__('app.sections.right_eye'))
                    ->columns(2)
                    ->schema(self::eyeFields('od')),
                Section::make(__('app.sections.left_eye'))
                    ->columns(2)
                    ->schema(self::eyeFields('os')),
            ]);
    }

    /**
     * Build the refraction fields for a single eye ('od' or 'os').
     *
     * @return array<int, Component>
     */
    protected static function eyeFields(string $eye): array
    {
        return [
            self::signed("{$eye}_sphere", __('app.fields.sphere'), 20),
            self::signed("{$eye}_cylinder", __('app.fields.cylinder'), 10, requiredWith: "{$eye}_axis"),
            TextInput::make("{$eye}_axis")
                ->label(__('app.fields.axis'))
                ->numeric()
                ->minValue(1)
                ->maxValue(180)
                ->integer()
                ->requiredWith("{$eye}_cylinder_num"),
            TextInput::make("{$eye}_add_num")
                ->label(__('app.fields.add'))
                ->numeric()
                ->prefix('+')
                ->rule(new Diopter(0.25, 4)),
            TextInput::make("{$eye}_va")
                ->label(__('app.fields.va')),
            TextInput::make("{$eye}_pd")
                ->label(__('app.fields.pd'))
                ->numeric()
                ->minValue(20)
                ->maxValue(40),
        ];
    }

    /**
     * A signed diopter field: the +/- toggle fused next to the magnitude input.
     */
    protected static function signed(string $field, string $label, float $max, ?string $requiredWith = null): FusedGroup
    {
        $magnitude = TextInput::make("{$field}_num")
            ->placeholder($label)
            ->numeric()
            ->minValue(0)
            ->maxValue($max)
            ->rule(new Diopter(0, $max));

        if ($requiredWith !== null) {
            $magnitude->requiredWith($requiredWith);
        }

        return FusedGroup::make([
            ToggleButtons::make("{$field}_sign")
                ->options(['+' => '+', '-' => '−'])
                ->default('-')
                ->inline(),
            $magnitude,
        ])
            ->label($label)
            ->columns(['default' => 2]);
    }
}
