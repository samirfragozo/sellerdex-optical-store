<?php

namespace App\Filament\Resources\LensPackages\Pages;

use App\Filament\Concerns\RedirectsToResourceIndex;
use App\Filament\Resources\LensPackages\LensPackageResource;
use Filament\Resources\Pages\CreateRecord;

class CreateLensPackage extends CreateRecord
{
    use RedirectsToResourceIndex;

    protected static string $resource = LensPackageResource::class;
}
