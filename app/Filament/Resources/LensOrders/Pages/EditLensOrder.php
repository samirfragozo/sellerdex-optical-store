<?php

namespace App\Filament\Resources\LensOrders\Pages;

use App\Filament\Concerns\RedirectsToResourceIndex;
use App\Filament\Resources\LensOrders\Actions\LabOrderActions;
use App\Filament\Resources\LensOrders\LensOrderResource;
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

        return [
            // Sending uses what is on screen: save pending edits first (a validation error stops the send).
            LabOrderActions::send()
                ->before(fn () => $this->save(shouldRedirect: false, shouldSendSavedNotification: false))
                ->after($refresh),
            LabOrderActions::receive()->after($refresh),
            LabOrderActions::ready()->after($refresh),
            LabOrderActions::remake()->after($refresh),
            DeleteAction::make(),
        ];
    }
}
