<?php

namespace App\Filament\Resources\Sales\Pages;

use App\Enums\SaleDocumentType;
use App\Filament\Concerns\RedirectsToResourceIndex;
use App\Filament\Resources\Sales\SaleResource;
use App\Models\Sale;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

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
                ->action(function (Sale $record): void {
                    $record->update(['document_type' => SaleDocumentType::Order]);
                    $record->recalculateStatus();

                    Notification::make()
                        ->success()
                        ->title(__('app.sale_actions.converted'))
                        ->send();
                }),
            DeleteAction::make(),
            ForceDeleteAction::make(),
            RestoreAction::make(),
        ];
    }
}
