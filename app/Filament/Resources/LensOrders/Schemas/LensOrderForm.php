<?php

namespace App\Filament\Resources\LensOrders\Schemas;

use App\Enums\LensOrderStatus;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class LensOrderForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('sale_item_id')
                    ->label(__('app.fields.sale_item'))
                    ->relationship(name: 'saleItem')
                    ->getOptionLabelFromRecordUsing(fn (Model $record) => 'Venta '.$record->sale?->number.' — '.$record->description)
                    ->searchable()
                    ->required(),
                Select::make('supplier_id')
                    ->label(__('app.fields.laboratory'))
                    ->relationship(
                        name: 'supplier',
                        titleAttribute: 'name',
                        modifyQueryUsing: fn (Builder $query) => $query->laboratories(),
                    )
                    ->searchable()
                    ->required(),
                Select::make('lab_status')
                    ->label(__('app.fields.lab_status'))
                    ->options(LensOrderStatus::options())
                    ->default(LensOrderStatus::Sent->value)
                    ->required(),
                DatePicker::make('expected_date')
                    ->label(__('app.fields.expected_date')),
                DatePicker::make('received_date')
                    ->label(__('app.fields.received_date')),
                Textarea::make('notes')
                    ->label(__('app.fields.notes'))
                    ->columnSpanFull(),
            ]);
    }
}
