<?php

namespace App\Filament\Resources\LensCombinations\RelationManagers;

use App\Models\LensCombinationPrice;
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

    public function form(Schema $schema): Schema
    {
        $diopter = fn (string $name, int $limit): TextInput => TextInput::make($name)
            ->label(__("app.fields.{$name}"))
            ->numeric()->step(0.25)->minValue(-$limit)->maxValue($limit)
            ->default(LensCombinationPrice::ALL_PRESCRIPTIONS[$name])->required();

        return $schema
            ->columns(2)
            ->components([
                Select::make('supplier_id')
                    ->label(__('app.fields.laboratory'))
                    ->relationship('supplier', 'name', fn (Builder $query) => $query->where('is_laboratory', true)->where('is_active', true))
                    ->required()
                    ->columnSpanFull()
                    ->helperText(__('app.resources.lens_combination.prices_help')),
                $diopter('sphere_min', 20),
                $diopter('sphere_max', 20)->gte('sphere_min'),
                $diopter('cylinder_min', 10),
                $diopter('cylinder_max', 10)->gte('cylinder_min'),
                TextInput::make('add_min')->label(__('app.fields.add_min'))->numeric()->step(0.25)->minValue(0)->maxValue(4),
                // ponytail: gte() takes no condition in Filament v4, so the "only when both are filled" check is a rule.
                TextInput::make('add_max')->label(__('app.fields.add_max'))->numeric()->step(0.25)->minValue(0)->maxValue(4)
                    ->rule(fn (Get $get): Closure => function (string $attribute, mixed $value, Closure $fail) use ($get): void {
                        $min = $get('add_min');

                        if (filled($min) && filled($value) && (float) $value < (float) $min) {
                            $fail(__('validation.gte.numeric', ['attribute' => __('app.fields.add_max'), 'value' => $min]));
                        }
                    }),
                TextInput::make('cost')->label(__('app.fields.cost'))->numeric()->minValue(0)->prefix('$')->required(),
                TextInput::make('price')->label(__('app.fields.price'))->numeric()->minValue(1)->prefix('$')->required(),
                Toggle::make('is_preferred')
                    ->label(__('app.fields.is_preferred_lab'))
                    ->helperText(__('app.resources.lens_combination.preferred_help')),
                Toggle::make('is_active')->label(__('app.fields.active'))->default(true),
            ]);
    }

    public function table(Table $table): Table
    {
        $range = fn (string $from, string $to) => fn (LensCombinationPrice $record): string => $record->{$from} === null && $record->{$to} === null
            ? '—'
            : sprintf('%s … %s', $record->{$from} ?? '—', $record->{$to} ?? '—');

        return $table
            ->columns([
                TextColumn::make('supplier.name')->label(__('app.fields.laboratory')),
                TextColumn::make('sphere_range')->label(__('app.fields.sphere'))->state($range('sphere_min', 'sphere_max')),
                TextColumn::make('cylinder_range')->label(__('app.fields.cylinder'))->state($range('cylinder_min', 'cylinder_max')),
                TextColumn::make('add_range')->label(__('app.fields.add'))->state($range('add_min', 'add_max')),
                TextColumn::make('cost')->label(__('app.fields.cost'))->money('COP'),
                TextColumn::make('price')->label(__('app.fields.price'))->money('COP'),
                IconColumn::make('is_preferred')->label(__('app.fields.is_preferred_lab'))->boolean(),
                IconColumn::make('is_active')->label(__('app.fields.active'))->boolean(),
            ])
            ->headerActions([CreateAction::make()])
            ->recordActions([EditAction::make(), DeleteAction::make()]);
    }
}
