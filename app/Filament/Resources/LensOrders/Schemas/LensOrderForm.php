<?php

namespace App\Filament\Resources\LensOrders\Schemas;

use App\Enums\FrameSource;
use App\Enums\FrameType;
use App\Enums\LensOrderStatus;
use App\Enums\RemakeReason;
use App\Enums\RemakeResponsible;
use App\Models\LensOrder;
use App\Models\Prescription;
use Closure;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class LensOrderForm
{
    public static function configure(Schema $schema): Schema
    {
        // The lab gets the technical data when the order is sent; after that it is read-only.
        $locked = fn (?LensOrder $record): bool => $record !== null && $record->lab_status !== LensOrderStatus::PendingAssignment;

        return $schema
            ->components([
                Select::make('sale_item_id')
                    ->label(__('app.fields.sale_item'))
                    ->relationship(name: 'saleItem')
                    ->getOptionLabelFromRecordUsing(fn (Model $record) => __('app.resources.sale.label').' '.$record->sale?->number.' — '.$record->description)
                    ->searchable()
                    ->required()
                    ->disabledOn('edit')
                    // Remakes share the sale item, so this is checked on create only (no plain unique).
                    ->rules([fn (): Closure => function (string $attribute, mixed $value, Closure $fail): void {
                        if (LensOrder::where('sale_item_id', $value)->exists()) {
                            $fail(__('app.lab_order.item_has_order'));
                        }
                    }], fn (string $operation): bool => $operation === 'create'),
                Select::make('supplier_id')
                    ->label(__('app.fields.laboratory'))
                    ->relationship(
                        name: 'supplier',
                        titleAttribute: 'name',
                        modifyQueryUsing: fn (Builder $query) => $query->laboratories(),
                    )
                    ->searchable()
                    ->disabled($locked),
                // The status moves only through the workflow actions; the model starts new orders pending.
                Select::make('lab_status')
                    ->label(__('app.fields.lab_status'))
                    ->options(LensOrderStatus::options())
                    ->default(LensOrderStatus::PendingAssignment->value)
                    ->disabled()
                    ->dehydrated(false),
                DatePicker::make('expected_date')
                    ->label(__('app.fields.expected_date')),
                DatePicker::make('received_date')
                    ->label(__('app.fields.received_date')),
                Section::make(__('app.lab_order.sections.measurements'))
                    ->columns(4)
                    ->columnSpanFull()
                    ->disabled($locked)
                    ->schema([
                        self::measure('od_pd', 20, 40),
                        self::measure('os_pd', 20, 40),
                        self::measure('od_height', 10, 40),
                        self::measure('os_height', 10, 40),
                        self::measure('frame_a', 30, 80),
                        self::measure('frame_b', 15, 60),
                        self::measure('frame_dbl', 10, 30),
                        Select::make('frame_type')
                            ->label(__('app.fields.frame_type'))
                            ->options(FrameType::options()),
                    ]),
                Section::make(__('app.lab_order.sections.frame'))
                    ->columns(2)
                    ->columnSpanFull()
                    ->disabled($locked)
                    ->schema([
                        Select::make('frame_source')
                            ->label(__('app.fields.frame_source'))
                            ->options(FrameSource::options())
                            ->disabled()
                            ->dehydrated(false)
                            ->live()
                            ->columnSpanFull(),
                        TextInput::make('customer_frame_description')
                            ->label(__('app.fields.customer_frame_description'))
                            ->maxLength(255)
                            ->visible(fn (Get $get): bool => $get('frame_source') === FrameSource::CustomerOwn->value),
                        TextInput::make('customer_frame_condition')
                            ->label(__('app.fields.customer_frame_condition'))
                            ->maxLength(255)
                            ->visible(fn (Get $get): bool => $get('frame_source') === FrameSource::CustomerOwn->value),
                    ]),
                Section::make(__('app.lab_order.sections.prescription'))
                    ->columns(2)
                    ->columnSpanFull()
                    ->visible(fn (?LensOrder $record): bool => filled($record?->prescription_snapshot))
                    ->schema([
                        self::eye('od', __('app.sections.right_eye')),
                        self::eye('os', __('app.sections.left_eye')),
                    ]),
                Section::make(__('app.lab_order.is_remake'))
                    ->columns(3)
                    ->columnSpanFull()
                    ->visible(fn (?LensOrder $record): bool => $record?->remake_of_id !== null)
                    ->schema([
                        TextEntry::make('remake_of_id')
                            ->hiddenLabel()
                            ->state(fn (LensOrder $record): string => __('app.lab_order.remake_of', ['id' => $record->remake_of_id]))
                            ->columnSpanFull(),
                        Select::make('remake_reason')
                            ->label(__('app.fields.remake_reason'))
                            ->options(RemakeReason::options())
                            ->disabled()->dehydrated(false),
                        Select::make('remake_responsible')
                            ->label(__('app.fields.remake_responsible'))
                            ->options(RemakeResponsible::options())
                            ->disabled()->dehydrated(false),
                        TextInput::make('remake_cost')
                            ->label(__('app.fields.remake_cost'))
                            ->prefix('$')
                            ->disabled()->dehydrated(false),
                    ]),
                Textarea::make('notes')
                    ->label(__('app.fields.notes'))
                    ->maxLength(1000)
                    ->columnSpanFull(),
            ]);
    }

    private static function measure(string $field, float $min, float $max): TextInput
    {
        return TextInput::make($field)
            ->label(__("app.fields.{$field}"))
            ->numeric()
            ->step(0.5)
            ->minValue($min)
            ->maxValue($max);
    }

    /** One read-only line with an eye's prescription as it was sent to the lab. */
    private static function eye(string $eye, string $label): TextEntry
    {
        return TextEntry::make("prescription_snapshot_{$eye}")
            ->label($label)
            ->state(function (LensOrder $record) use ($eye): string {
                $snapshot = $record->prescription_snapshot ?? [];

                return collect(['sphere', 'cylinder', 'axis', 'add'])
                    ->map(fn (string $field): string => __("app.fields.{$field}").' '.($field === 'axis'
                        ? ($snapshot["{$eye}_{$field}"] ?? '—')
                        : (Prescription::formatDiopter($snapshot["{$eye}_{$field}"] ?? null) ?: '—')))
                    ->implode('  ·  ');
            });
    }
}
