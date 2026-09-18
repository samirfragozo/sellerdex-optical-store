<?php

namespace App\Filament\Resources\LensTypes\Pages;

use App\Filament\Concerns\RedirectsToResourceIndex;
use App\Filament\Resources\LensTypes\LensTypeResource;
use Filament\Resources\Pages\CreateRecord;

class CreateLensType extends CreateRecord
{
    use RedirectsToResourceIndex;

    protected static string $resource = LensTypeResource::class;
}
