<?php

namespace App\Filament\Resources\Sales\Tables;

use App\Actions\ConvertQuoteToOrder;
use App\Enums\FiscalDocumentType;
use App\Enums\SaleDocumentType;
use App\Enums\SaleStatus;
use App\Filament\Resources\Sales\Actions\FiscalDocumentActions;
use App\Filament\Resources\Sales\Actions\SaleReturnActions;
use App\Models\Customer;
use App\Models\FiscalDocument;
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
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SalesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with('fiscalDocuments'))
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
                TextColumn::make('fiscal_document_number')
                    ->label(__('app.fiscal_document.column'))
                    ->state(fn (Sale $record): ?string => self::shownDocument($record)?->number)
                    ->url(fn (Sale $record): ?string => ($document = self::shownDocument($record))?->pdf_path !== null
                        ? route('documents.fiscal-document.pdf', $document)
                        : null, shouldOpenInNewTab: true)
                    ->placeholder('—')
                    ->toggleable(),
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
                Filter::make('missing_fiscal_document')
                    ->label(__('app.fiscal_document.missing_filter'))
                    ->query(fn (Builder $query): Builder => $query->missingFiscalDocument())
                    ->toggle(),
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
                            if (! $record->canBeDelivered()) {
                                Notification::make()
                                    ->danger()
                                    ->title(__('app.sale_actions.cannot_deliver_pending_lens'))
                                    ->send();

                                return;
                            }

                            $record->update(['is_delivered' => true, 'delivered_at' => now()]);

                            Notification::make()->success()->title(__('app.sale_actions.delivered'))->send();
                        }),
                    Action::make('convertToOrder')
                        ->label(__('app.sale_actions.convert_to_order'))
                        ->icon('heroicon-o-arrow-path')
                        ->visible(fn (Sale $record): bool => $record->document_type === SaleDocumentType::Quote)
                        ->requiresConfirmation()
                        ->modalDescription(fn (Sale $record): ?string => $record->isExpiredQuote() ? __('app.sale_actions.convert_expired_hint') : null)
                        ->action(function (Sale $record): void {
                            try {
                                $repriced = app(ConvertQuoteToOrder::class)->handle($record);
                            } catch (ValidationException $exception) {
                                Notification::make()->danger()->title(__('app.sale_actions.reprice_failed'))
                                    ->body(collect($exception->errors())->flatten()->first())->send();

                                return;
                            }

                            Notification::make()->success()
                                ->title($repriced
                                    ? __('app.sale_actions.converted_repriced', ['total' => '$'.number_format($record->fresh()->total, 0, ',', '.')])
                                    : __('app.sale_actions.converted'))
                                ->send();
                        }),
                    FiscalDocumentActions::registerForSale(),
                    FiscalDocumentActions::invoiceData(),
                    ...SaleReturnActions::make(),
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

    /** The sale's factura or POS electrónico, else its receipt, read from the eager-loaded relation. */
    private static function shownDocument(Sale $sale): ?FiscalDocument
    {
        $documents = $sale->fiscalDocuments->whereNull('sale_return_id');

        return $documents->first(fn (FiscalDocument $document): bool => in_array($document->document_type, [FiscalDocumentType::PosElectronic, FiscalDocumentType::ElectronicInvoice], true))
            ?? $documents->first(fn (FiscalDocument $document): bool => $document->document_type === FiscalDocumentType::Receipt);
    }
}
