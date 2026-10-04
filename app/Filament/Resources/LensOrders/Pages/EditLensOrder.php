<?php

namespace App\Filament\Resources\LensOrders\Pages;

use App\Enums\LensOrderStatus;
use App\Filament\Concerns\RedirectsToResourceIndex;
use App\Filament\Resources\LensOrders\Actions\LabOrderActions;
use App\Filament\Resources\LensOrders\LensOrderResource;
use App\Models\LensOrder;
use App\Support\LabOrderMessage;
use App\Support\WhatsApp;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditLensOrder extends EditRecord
{
    use RedirectsToResourceIndex;

    protected static string $resource = LensOrderResource::class;

    protected function getHeaderActions(): array
    {
        // The actions change status and dates: reload them into the form so a later save doesn't overwrite them.
        $refresh = fn () => $this->refreshFormData([
            'supplier_id', 'lab_status', 'expected_date', 'received_date', 'sent_at',
            'od_pd', 'os_pd', 'od_height', 'os_height', 'frame_a', 'frame_b', 'frame_dbl', 'frame_type',
        ]);

        // Until "Mark as sent" the saved record can differ from the screen, so nothing leaves for the lab.
        $isSent = fn (LensOrder $record): bool => $record->lab_status !== LensOrderStatus::PendingAssignment;

        return [
            Action::make('printLabOrder')->label(__('app.lab_order.actions.print'))->icon('heroicon-o-printer')
                ->visible($isSent)
                ->url(fn (LensOrder $record): string => route('documents.lab-order', $record))->openUrlInNewTab(),
            Action::make('downloadLabOrder')->label(__('app.lab_order.actions.download'))->icon('heroicon-o-arrow-down-tray')
                ->visible($isSent)
                ->url(fn (LensOrder $record): string => route('documents.lab-order.pdf', $record)),
            Action::make('whatsappLab')->label(__('app.lab_order.actions.whatsapp'))->icon('heroicon-o-chat-bubble-left-right')
                ->visible(fn (LensOrder $record): bool => $isSent($record) && WhatsApp::url($record->supplier?->phone, '') !== null)
                ->url(fn (LensOrder $record): ?string => WhatsApp::url($record->supplier?->phone, LabOrderMessage::for($record)))->openUrlInNewTab(),
            Action::make('emailLab')->label(__('app.lab_order.actions.email'))->icon('heroicon-o-envelope')
                ->visible(fn (LensOrder $record): bool => $isSent($record) && LabOrderMessage::mailtoUrl($record) !== null)
                ->url(fn (LensOrder $record): ?string => LabOrderMessage::mailtoUrl($record)),
            // Sending uses what is on screen: save pending edits first (a validation error stops the send).
            LabOrderActions::send()
                ->before(fn () => $this->save(shouldRedirect: false, shouldSendSavedNotification: false))
                ->after($refresh),
            LabOrderActions::receive()->after($refresh),
            LabOrderActions::ready()->after($refresh),
            LabOrderActions::notifyCustomer(),
            LabOrderActions::remake()->after($refresh),
            DeleteAction::make(),
        ];
    }
}
