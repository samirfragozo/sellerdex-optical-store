<?php

namespace App\Filament\Resources\LensTreatments\Pages;

use App\Filament\Concerns\RedirectsToResourceIndex;
use App\Filament\Resources\LensTreatments\LensTreatmentResource;
use Filament\Resources\Pages\CreateRecord;

class CreateLensTreatment extends CreateRecord
{
    use RedirectsToResourceIndex;

    protected static string $resource = LensTreatmentResource::class;
}
