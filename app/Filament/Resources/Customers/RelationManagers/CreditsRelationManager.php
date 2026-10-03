<?php

namespace App\Filament\Resources\Customers\RelationManagers;

use App\Models\CustomerCredit;
use App\Models\Payment;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/** Read-only store-credit ledger of a customer; the heading carries the current balance. */
class CreditsRelationManager extends RelationManager
{
    protected static string $relationship = 'credits';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('app.relations.customer_credits').': $'.number_format($ownerRecord->creditBalance(), 0, ',', '.');
    }

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('created_at')
                    ->label(__('app.fields.date'))
                    ->dateTime(),
                TextColumn::make('amount')
                    ->label(__('app.fields.amount'))
                    ->money('COP')
                    ->color(fn (int $state): string => $state < 0 ? 'danger' : 'success')
                    ->alignEnd(),
                TextColumn::make('source')
                    ->label(__('app.sale_return.fields.source'))
                    ->state(fn (CustomerCredit $record): string => $this->sourceLabel($record)),
            ]);
    }

    /** "Venta 000123" for the sale whose payment moved the credit. */
    private function sourceLabel(CustomerCredit $credit): string
    {
        if ($credit->source_type !== (new Payment)->getMorphClass()) {
            return '';
        }

        // The payment or its sale may have been deleted since; the ledger keeps the entry either way.
        $sale = Payment::withoutGlobalScopes()->with(['sale' => fn ($query) => $query->withoutGlobalScopes()])->find($credit->source_id)?->sale;

        return $sale === null ? '' : __('app.inventory.sale_source', ['number' => $sale->number]);
    }
}
