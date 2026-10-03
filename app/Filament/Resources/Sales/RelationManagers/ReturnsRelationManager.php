<?php

namespace App\Filament\Resources\Sales\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/** Read-only history of the returns, value adjustments and voids of a sale. */
class ReturnsRelationManager extends RelationManager
{
    protected static string $relationship = 'returns';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('app.relations.sale_returns');
    }

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('created_at')
                    ->label(__('app.fields.date'))
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('type')
                    ->label(__('app.fields.type'))
                    ->badge(),
                TextColumn::make('reason')
                    ->label(__('app.sale_return.fields.reason'))
                    ->wrap(),
                TextColumn::make('total')
                    ->label(__('app.fields.total'))
                    ->money('COP')
                    ->alignEnd(),
                TextColumn::make('refund_amount')
                    ->label(__('app.sale_return.fields.refund_amount'))
                    ->money('COP')
                    ->alignEnd(),
                TextColumn::make('store_credit_amount')
                    ->label(__('app.sale_return.fields.store_credit_amount'))
                    ->money('COP')
                    ->alignEnd(),
                TextColumn::make('retained_amount')
                    ->label(__('app.sale_return.fields.retained_amount'))
                    ->money('COP')
                    ->alignEnd(),
                // Lens cost lost is internal, like the unit cost.
                TextColumn::make('loss_amount')
                    ->label(__('app.sale_return.fields.loss_amount'))
                    ->money('COP')
                    ->alignEnd()
                    ->visible(fn (): bool => auth()->user()?->isAdmin() === true),
                TextColumn::make('user.name')
                    ->label(__('app.sale_return.fields.user')),
            ]);
    }
}
