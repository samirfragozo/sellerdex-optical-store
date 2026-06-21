<?php

namespace App\Filament\Widgets;

use App\Models\Sale;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class AccountsReceivableWidget extends BaseWidget
{
    public function table(Table $table): Table
    {
        return $table
            ->heading(__('app.reports.receivable'))
            ->query(
                Sale::query()
                    ->withSum('payments as paid_total', 'amount')
                    ->havingRaw('total - COALESCE(paid_total, 0) > 0')
            )
            ->columns([
                TextColumn::make('number')
                    ->label(__('app.fields.number')),
                TextColumn::make('customer.name')
                    ->label(__('app.fields.customer')),
                TextColumn::make('total')
                    ->label(__('app.fields.total'))
                    ->money('COP'),
                TextColumn::make('paid_total')
                    ->label(__('app.fields.total_collected'))
                    ->money('COP'),
                TextColumn::make('balance')
                    ->label(__('app.fields.balance'))
                    ->money('COP'),
                TextColumn::make('sold_at')
                    ->label(__('app.fields.sold_at'))
                    ->date(),
            ]);
    }
}
