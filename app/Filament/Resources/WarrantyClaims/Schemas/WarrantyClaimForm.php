<?php

namespace App\Filament\Resources\WarrantyClaims\Schemas;

use App\Enums\WarrantyClaimType;
use App\Models\Sale;
use App\Models\SaleItem;
use Carbon\CarbonInterface;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;

class WarrantyClaimForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('sale_id')
                    ->label(__('app.fields.sale'))
                    ->getSearchResultsUsing(fn (string $search): array => Sale::query()
                        ->where('is_delivered', true)
                        ->where(fn (Builder $query): Builder => $query
                            ->where('number', 'like', "%{$search}%")
                            ->orWhereHas('customer', fn (Builder $customer): Builder => $customer
                                ->where('name', 'like', "%{$search}%")
                                ->orWhere('last_name', 'like', "%{$search}%")))
                        ->with('customer')->latest('delivered_at')->limit(50)->get()
                        ->mapWithKeys(fn (Sale $sale): array => [$sale->id => self::saleLabel($sale)])->all())
                    ->getOptionLabelUsing(fn ($value): ?string => ($sale = Sale::query()->with('customer')->find($value)) ? self::saleLabel($sale) : null)
                    ->searchable()
                    ->live()
                    ->required()
                    ->dehydrated(false),
                Select::make('sale_item_id')
                    ->label(__('app.fields.item'))
                    ->options(fn (Get $get): array => SaleItem::query()->where('sale_id', $get('sale_id'))->pluck('description', 'id')->all())
                    ->required(),
                Select::make('type')
                    ->label(__('app.fields.type'))
                    ->options(WarrantyClaimType::options())
                    ->default(WarrantyClaimType::Warranty->value)
                    ->required(),
                DatePicker::make('received_at')
                    ->label(__('app.fields.received_at'))
                    ->default(now())
                    ->minDate(fn (Get $get): ?CarbonInterface => $get('sale_id') ? Sale::query()->find($get('sale_id'))?->delivered_at : null)
                    ->maxDate(now())
                    ->required(),
                Textarea::make('customer_description')
                    ->label(__('app.warranty.fields.customer_description'))
                    ->required()
                    ->maxLength(2000)
                    ->columnSpanFull(),
            ]);
    }

    private static function saleLabel(Sale $sale): string
    {
        return $sale->number.' — '.($sale->customer?->full_name ?? '—');
    }
}
