<?php

namespace App\Filament\Resources\LensTreatments\Pages;

use App\Filament\Concerns\RedirectsToResourceIndex;
use App\Filament\Resources\LensTreatments\LensTreatmentResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditLensTreatment extends EditRecord
{
    use RedirectsToResourceIndex;

    protected static string $resource = LensTreatmentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
