<?php

namespace App\Filament\Resources\LensCombinations\RelationManagers;

use App\Models\LensCombinationPrice;
use App\Models\Prescription;
use Closure;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class PricesRelationManager extends RelationManager
{
    protected static string $relationship = 'prices';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('app.relations.lens_prices');
    }

    /**
     * Sphere, cylinder and addition min-max inputs, defaulting to "all prescriptions"
     * and bounded by the manufacturing limits. Shared with the sale price bands.
     *
     * @return array<int, TextInput>
     */
    public static function rangeFields(): array
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

    public function form(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                Select::make('supplier_id')
                    ->label(__('app.fields.laboratory'))
                    ->relationship('supplier', 'name', fn (Builder $query) => $query->where('is_laboratory', true)->where('is_active', true))
                    ->required()
                    ->columnSpanFull()
                    ->helperText(__('app.resources.lens_combination.prices_help')),
                ...self::rangeFields(),
                TextInput::make('cost')->label(__('app.fields.cost'))->numeric()->minValue(0)->prefix('$')->required(),
                Toggle::make('is_preferred')
                    ->label(__('app.fields.is_preferred_lab'))
                    ->helperText(__('app.resources.lens_combination.preferred_help')),
                Toggle::make('is_active')->label(__('app.fields.active'))->default(true),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('supplier.name')->label(__('app.fields.laboratory')),
                ...self::rangeColumns(),
                TextColumn::make('cost')->label(__('app.fields.cost'))->money('COP'),
                IconColumn::make('is_preferred')->label(__('app.fields.is_preferred_lab'))->boolean(),
                IconColumn::make('is_active')->label(__('app.fields.active'))->boolean(),
            ])
            ->headerActions([CreateAction::make()])
            ->recordActions([EditAction::make(), DeleteAction::make()]);
    }
}
