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
        $refresh = fn () => $this->refreshFormData(['lab_status', 'expected_date', 'received_date', 'sent_at']);

        return [
            LabOrderActions::send()->after($refresh),
            LabOrderActions::receive()->after($refresh),
            LabOrderActions::ready()->after($refresh),
            LabOrderActions::remake()->after($refresh),
            DeleteAction::make(),
        ];
    }
}
