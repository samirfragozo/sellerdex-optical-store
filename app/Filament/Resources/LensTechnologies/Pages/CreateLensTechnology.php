<?php

namespace App\Filament\Resources\LensTechnologies\Pages;

use App\Filament\Concerns\RedirectsToResourceIndex;
use App\Filament\Resources\LensTechnologies\LensTechnologyResource;
use Filament\Resources\Pages\CreateRecord;

class CreateLensTechnology extends CreateRecord
{
    use RedirectsToResourceIndex;

    protected static string $resource = LensTechnologyResource::class;
}
