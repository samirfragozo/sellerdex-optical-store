<?php

namespace App\Filament\Resources\Sales\Schemas;

use App\Enums\SaleDocumentType;
use App\Models\Sale;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class SaleForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('customer_id')
                    ->label(__('app.fields.customer'))
                    ->relationship('customer', 'name')
                    ->searchable()
                    ->preload()
                    ->required(),
                Select::make('document_type')
                    ->label(__('app.fields.document_type_sale'))
                    ->options(SaleDocumentType::options())
                    ->default(SaleDocumentType::Order->value)
                    ->required()
                    // Locked after creation: changing it would desync stock and numbering.
                    ->disabledOn('edit')
                    ->dehydrated(),
                Select::make('prescription_id')
                    ->label(__('app.fields.prescription'))
                    ->relationship('prescription', 'id')
                    ->searchable(),
                DatePicker::make('sold_at')
                    ->label(__('app.fields.sold_at'))
                    ->default(now())
                    ->required(),
                TextInput::make('discount')
                    ->label(__('app.fields.discount'))
                    ->numeric()
                    ->default(0)
                    ->prefix('$'),
                Placeholder::make('total')
                    ->label(__('app.fields.total'))
                    ->visibleOn('edit')
                    ->content(fn (?Sale $record): string => $record ? '$'.number_format($record->total, 0, ',', '.') : '—'),
                Placeholder::make('balance')
                    ->label(__('app.fields.balance'))
                    ->visibleOn('edit')
                    ->content(fn (?Sale $record): string => $record ? '$'.number_format($record->balance, 0, ',', '.') : '—'),
                Textarea::make('notes')
                    ->label(__('app.fields.notes'))
                    ->columnSpanFull(),
                Section::make(__('app.sections.options'))
                    ->columns(2)
                    ->schema([
                        Toggle::make('is_delivered')
                            ->label(__('app.fields.is_delivered')),
                        DatePicker::make('delivered_at')
                            ->label(__('app.fields.delivered_at'))
                            ->default(now())
                            ->visible(fn ($get): bool => (bool) $get('is_delivered')),
                    ]),
            ]);
    }
}
