<?php

namespace App\Filament\Resources\Sales\Tables;

use App\Enums\SaleDocumentType;
use App\Enums\SaleStatus;
use App\Models\Customer;
use App\Models\Sale;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class SalesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('sold_at', 'desc')
            ->columns([
                TextColumn::make('number')
                    ->label(__('app.fields.number'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('customer.name')
                    ->label(__('app.fields.customer'))
                    ->searchable()
                    ->sortable(query: fn (Builder $query, string $direction): Builder => $query->orderBy(
                        Customer::select('name')->whereColumn('customers.id', 'sales.customer_id'),
                        $direction,
                    )),
                TextColumn::make('document_type')
                    ->label(__('app.fields.document_type_sale'))
                    ->badge()
                    ->sortable(),
                TextColumn::make('status')
                    ->label(__('app.fields.status'))
                    ->badge()
                    ->sortable(),
                TextColumn::make('total')
                    ->label(__('app.fields.total'))
                    ->money('COP')
                    ->sortable(),
                TextColumn::make('balance')
                    ->label(__('app.fields.balance'))
                    ->money('COP')
                    ->sortable(query: fn (Builder $query, string $direction): Builder => $query->orderBy(
                        DB::raw('sales.total - (select coalesce(sum(amount), 0) from payments where payments.sale_id = sales.id and payments.deleted_at is null)'),
                        $direction,
                    )),
                TextColumn::make('sold_at')
                    ->label(__('app.fields.sold_at'))
                    ->date()
                    ->sortable(),
                TextColumn::make('deleted_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('document_type')
                    ->label(__('app.fields.document_type_sale'))
                    ->options(SaleDocumentType::options()),
                SelectFilter::make('status')
                    ->label(__('app.fields.status'))
                    ->options(SaleStatus::options()),
                TrashedFilter::make(),
            ])
            ->recordActions([
                ActionGroup::make([
                    EditAction::make(),
                    Action::make('markDelivered')
                        ->label(__('app.sale_actions.mark_delivered'))
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->visible(fn (Sale $record): bool => ! $record->is_delivered && $record->status !== SaleStatus::Voided)
                        ->requiresConfirmation()
                        ->action(function (Sale $record): void {
                            $record->update(['is_delivered' => true, 'delivered_at' => now()]);

                            Notification::make()->success()->title(__('app.sale_actions.delivered'))->send();
                        }),
                    Action::make('convertToOrder')
                        ->label(__('app.sale_actions.convert_to_order'))
                        ->icon('heroicon-o-arrow-path')
                        ->visible(fn (Sale $record): bool => $record->document_type === SaleDocumentType::Quote)
                        ->requiresConfirmation()
                        ->action(function (Sale $record): void {
                            $record->update(['document_type' => SaleDocumentType::Order]);
                            $record->recalculateStatus();

                            Notification::make()->success()->title(__('app.sale_actions.converted'))->send();
                        }),
                    Action::make('printInvoice')
                        ->label(__('app.documents.print_invoice'))
                        ->icon('heroicon-o-printer')
                        ->url(fn (Sale $record) => route('documents.invoice', $record))
                        ->openUrlInNewTab(),
                    Action::make('downloadInvoice')
                        ->label(__('app.documents.download_invoice'))
                        ->icon('heroicon-o-arrow-down-tray')
                        ->url(fn (Sale $record) => route('documents.invoice.pdf', $record)),
                ]),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]),
            ]);
    }
}
