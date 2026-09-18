<?php

namespace App\Filament\Resources\LensMaterials\Pages;

use App\Filament\Concerns\RedirectsToResourceIndex;
use App\Filament\Resources\LensMaterials\LensMaterialResource;
use Filament\Resources\Pages\CreateRecord;

class CreateLensMaterial extends CreateRecord
{
    use RedirectsToResourceIndex;

    protected static string $resource = LensMaterialResource::class;
}
