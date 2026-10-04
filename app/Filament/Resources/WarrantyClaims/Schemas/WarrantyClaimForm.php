<?php

namespace App\Filament\Resources\WarrantyClaims\Schemas;

use App\Enums\WarrantyClaimType;
use App\Models\Sale;
use App\Models\SaleItem;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

class WarrantyClaimForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('sale_id')
                    ->label(__('app.fields.sale'))
                    ->options(fn (): array => Sale::query()->where('is_delivered', true)->latest('delivered_at')->limit(200)
                        ->with('customer')->get()
                        ->mapWithKeys(fn (Sale $sale): array => [$sale->id => $sale->number.' — '.($sale->customer?->full_name ?? '—')])->all())
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
                    ->maxDate(now())
                    ->required(),
                Textarea::make('customer_description')
                    ->label(__('app.warranty.fields.customer_description'))
                    ->required()
                    ->maxLength(2000)
                    ->columnSpanFull(),
            ]);
    }
}
