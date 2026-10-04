<?php

namespace App\Filament\Resources\Sales\Pages;

use App\Actions\ConvertQuoteToOrder;
use App\Enums\SaleDocumentType;
use App\Filament\Concerns\RedirectsToResourceIndex;
use App\Filament\Resources\Sales\Actions\SaleReturnActions;
use App\Filament\Resources\Sales\SaleResource;
use App\Models\Sale;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Validation\ValidationException;

class EditSale extends EditRecord
{
    use RedirectsToResourceIndex;

    protected static string $resource = SaleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('convertToOrder')
                ->label(__('app.sale_actions.convert_to_order'))
                ->icon('heroicon-o-arrow-path')
                ->color('success')
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

                    $this->refreshFormData(['total']);

                    Notification::make()->success()
                        ->title($repriced
                            ? __('app.sale_actions.converted_repriced', ['total' => '$'.number_format($record->fresh()->total, 0, ',', '.')])
                            : __('app.sale_actions.converted'))
                        ->send();
                }),
            ...SaleReturnActions::make(),
            DeleteAction::make(),
            // Money and returns are accounting records: a sale holding any of them is never wiped.
            ForceDeleteAction::make()
                ->hidden(fn (Sale $record): bool => ! $record->trashed() || $record->payments()->withTrashed()->exists() || $record->returns()->exists() || $record->fiscalDocuments()->exists()),
            RestoreAction::make(),
        ];
    }
}
