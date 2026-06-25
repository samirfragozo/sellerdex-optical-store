<?php

namespace App\Filament\Resources\Prescriptions\Pages;

use App\Filament\Concerns\RedirectsToResourceIndex;
use App\Filament\Resources\Prescriptions\Concerns\InteractsWithPrescriptionSigns;
use App\Filament\Resources\Prescriptions\PrescriptionResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;

class EditPrescription extends EditRecord
{
    use InteractsWithPrescriptionSigns;
    use RedirectsToResourceIndex;

    protected static string $resource = PrescriptionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
            ForceDeleteAction::make(),
            RestoreAction::make(),
        ];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        return $this->splitDiopterSigns($data);
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        return $this->combineDiopterSigns($data);
    }
}
