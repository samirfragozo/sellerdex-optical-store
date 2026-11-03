<?php

namespace App\Filament\Resources\LensCombinations\Concerns;

use App\Models\LensCombinationPrice;
use App\Models\Prescription;
use Closure;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Database\Eloquent\Model;

/** Prescription range inputs and columns shared by the price bands and the lab sheet rows and columns. */
trait PrescriptionRangeFields
{
    /**
     * Sphere, cylinder and addition min-max inputs, defaulting to "all prescriptions"
     * and bounded by the manufacturing limits.
     *
     * @return array<int, TextInput>
     */
    public static function rangeFields(): array
    {
        return [...self::sphereCylinderFields(), ...self::additionFields()];
    }

    /**
     * Only the sphere and cylinder inputs.
     *
     * @return array<int, TextInput>
     */
    public static function sphereCylinderFields(): array
    {
        $diopter = fn (string $name, string $scale): TextInput => TextInput::make($name)
            ->label(__("app.fields.{$name}"))
            ->numeric()->step(0.25)->minValue(Prescription::LIMITS[$scale][0])->maxValue(Prescription::LIMITS[$scale][1])
            ->default(LensCombinationPrice::ALL_PRESCRIPTIONS[$name])->required();

        return [
            $diopter('sphere_min', 'sphere'),
            $diopter('sphere_max', 'sphere')->gte('sphere_min'),
            $diopter('cylinder_min', 'cylinder'),
            $diopter('cylinder_max', 'cylinder')->gte('cylinder_min'),
        ];
    }

    /**
     * Optional addition min-max inputs (0 to the manufacturing limit).
     *
     * @return array<int, TextInput>
     */
    public static function additionFields(): array
    {
        return [
            TextInput::make('add_min')->label(__('app.fields.add_min'))->numeric()->step(0.25)->minValue(0)->maxValue(Prescription::LIMITS['add'][1]),
            // ponytail: gte() takes no condition in Filament v4, so the "only when both are filled" check is a rule.
            TextInput::make('add_max')->label(__('app.fields.add_max'))->numeric()->step(0.25)->minValue(0)->maxValue(Prescription::LIMITS['add'][1])
                ->rule(fn (Get $get): Closure => function (string $attribute, mixed $value, Closure $fail) use ($get): void {
                    $min = $get('add_min');

                    if (filled($min) && filled($value) && (float) $value < (float) $min) {
                        $fail(__('validation.gte.numeric', ['attribute' => __('app.fields.add_max'), 'value' => $min]));
                    }
                }),
        ];
    }

    /**
     * "min … max" columns of the three prescription ranges.
     *
     * @return array<int, TextColumn>
     */
    public static function rangeColumns(): array
    {
        $range = fn (string $from, string $to) => fn (Model $record): string => $record->{$from} === null && $record->{$to} === null
            ? '—'
            : sprintf('%s … %s', $record->{$from} ?? '—', $record->{$to} ?? '—');

        return [
            TextColumn::make('sphere_range')->label(__('app.fields.sphere'))->state($range('sphere_min', 'sphere_max')),
            TextColumn::make('cylinder_range')->label(__('app.fields.cylinder'))->state($range('cylinder_min', 'cylinder_max')),
            TextColumn::make('add_range')->label(__('app.fields.add'))->state($range('add_min', 'add_max')),
        ];
    }
}
