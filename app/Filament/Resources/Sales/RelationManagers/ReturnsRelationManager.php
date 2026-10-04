<?php

namespace App\Filament\Resources\Sales\RelationManagers;

use App\Actions\RegisterFiscalDocument;
use App\Enums\SaleReturnType;
use App\Filament\Resources\Sales\Actions\FiscalDocumentActions;
use App\Models\SaleReturn;
use Carbon\CarbonImmutable;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/** History of the returns, value adjustments and voids of a sale, where their notes are registered. */
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
                TextColumn::make('fiscal_document')
                    ->label(__('app.fiscal_document.column'))
                    ->state(fn (SaleReturn $record): ?string => $record->fiscalDocuments()->first()?->number)
                    ->placeholder('—')
                    ->url(fn (SaleReturn $record): ?string => ($note = $record->fiscalDocuments()->first())?->pdf_path !== null
                        ? route('documents.fiscal-document.pdf', $note)
                        : null)
                    ->openUrlInNewTab(),
            ])
            ->recordActions([
                Action::make('registerNote')
                    ->label(__('app.fiscal_document.actions.register_note'))
                    ->icon(Heroicon::OutlinedDocumentCheck)
                    ->visible(fn (SaleReturn $record): bool => FiscalDocumentActions::externalMode($record->sale)
                        && $record->type !== SaleReturnType::Void
                        && $record->sale->saleDocument() !== null
                        && ! $record->fiscalDocuments()->exists())
                    ->fillForm(['issued_at' => today()->toDateString()])
                    ->schema(FiscalDocumentActions::fields(withType: false))
                    ->action(function (SaleReturn $record, array $data): void {
                        FiscalDocumentActions::mapErrors(fn () => app(RegisterFiscalDocument::class)->forReturn(
                            $record, (string) $data['number'], CarbonImmutable::parse($data['issued_at']), auth()->user(),
                            $data['cufe_or_cude'] ?? null, $data['pdf_path'] ?? null,
                        ));
                        Notification::make()->success()->title(__('app.fiscal_document.registered'))->send();
                    }),
            ]);
    }
}
