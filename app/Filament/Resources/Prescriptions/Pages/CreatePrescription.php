<?php

namespace App\Filament\Resources\Prescriptions\Pages;

use App\Filament\Concerns\RedirectsToResourceIndex;
use App\Filament\Resources\Prescriptions\Concerns\InteractsWithPrescriptionSigns;
use App\Filament\Resources\Prescriptions\PrescriptionResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Auth;

class CreatePrescription extends CreateRecord
{
    use InteractsWithPrescriptionSigns;
    use RedirectsToResourceIndex;

    protected static string $resource = PrescriptionResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['created_by'] = Auth::id();

        return $this->combineDiopterSigns($data);
    }
}
