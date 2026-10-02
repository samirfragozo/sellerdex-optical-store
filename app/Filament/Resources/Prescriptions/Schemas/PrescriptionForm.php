<?php

namespace App\Filament\Resources\Prescriptions\Schemas;

use App\Enums\PrismBase;
use App\Models\Prescription;
use App\Rules\Diopter;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
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
                    ->required()
                    // Once sold on, re-pointing the prescription to another
                    // customer would desync it from the armado's patient.
                    ->disabled(fn (?Prescription $record): bool => $record?->lensConfigs()->exists() ?? false),
                DatePicker::make('exam_date')
                    ->label(__('app.fields.exam_date'))
                    ->required()
                    ->maxDate(now())
                    ->minDate(now()->subYears(2)),
                DatePicker::make('expires_at')
                    ->label(__('app.fields.expires_at'))
                    ->disabled()
                    ->dehydrated(false)
                    ->visibleOn('edit'),
                TextInput::make('prescriber_name')
                    ->label(__('app.fields.prescriber_name'))
                    ->required()
                    ->maxLength(255),
                TextInput::make('prescriber_license')
                    ->label(__('app.fields.prescriber_license'))
                    ->maxLength(50),
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
                Textarea::make('notes')
                    ->label(__('app.fields.notes'))
                    ->maxLength(1000)
                    ->columnSpanFull(),
                // Health data: private `local` disk only, served through documents.prescription.attachment.
                FileUpload::make('attachment')
                    ->label(__('app.fields.attachment'))
                    ->disk('local')
                    ->directory('prescriptions')
                    ->visibility('private')
                    // No SVG: it could carry script served from the app origin.
                    ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp', 'application/pdf'])
                    ->maxSize(10240)
                    ->openable()
                    ->columnSpanFull(),
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
                ->label(__('app.fields.va'))
                ->maxLength(10),
            TextInput::make("{$eye}_pd")
                ->label(__('app.fields.pd'))
                ->numeric()
                ->minValue(20)
                ->maxValue(40)
                ->rule(new Diopter(20, 40, 0.5)),
            TextInput::make("{$eye}_prism")
                ->label(__('app.fields.prism'))
                ->numeric()
                ->minValue(0)
                ->maxValue(10)
                ->rule(new Diopter(0, 10)),
            Select::make("{$eye}_prism_base")
                ->label(__('app.fields.prism_base'))
                ->options(PrismBase::options())
                ->requiredWith("{$eye}_prism"),
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
