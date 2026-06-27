<?php

namespace App\Filament\Resources\PurchaseOrders\Tables;

use App\Enums\PurchaseOrderStatus;
use App\Models\PurchaseOrder;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class PurchaseOrdersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('number')
                    ->label(__('app.fields.number'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('supplier.name')
                    ->label(__('app.fields.supplier'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('status')
                    ->label(__('app.fields.status'))
                    ->badge()
                    ->sortable(),
                TextColumn::make('total')
                    ->label(__('app.fields.total'))
                    ->money('COP')
                    ->sortable(),
                TextColumn::make('received_at')
                    ->label(__('app.fields.received_at'))
                    ->date()
                    ->sortable(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->recordActions([
                ActionGroup::make([
                    EditAction::make(),
                    Action::make('receive')
                        ->label(__('app.purchase_order_actions.receive'))
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->visible(fn (PurchaseOrder $record): bool => $record->status !== PurchaseOrderStatus::Received && $record->status !== PurchaseOrderStatus::Cancelled)
                        ->requiresConfirmation()
                        ->action(fn (PurchaseOrder $record) => $record->receive()),
                    Action::make('cancel')
                        ->label(__('app.purchase_order_actions.cancel'))
                        ->icon('heroicon-o-x-circle')
                        ->color('danger')
                        ->visible(fn (PurchaseOrder $record): bool => $record->status !== PurchaseOrderStatus::Cancelled)
                        ->requiresConfirmation()
                        ->action(fn (PurchaseOrder $record) => $record->cancel()),
                ]),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
