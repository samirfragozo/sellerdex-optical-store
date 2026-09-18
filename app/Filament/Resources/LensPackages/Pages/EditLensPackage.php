<?php

namespace App\Filament\Resources\LensPackages\Pages;

use App\Filament\Concerns\RedirectsToResourceIndex;
use App\Filament\Resources\LensPackages\LensPackageResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditLensPackage extends EditRecord
{
    use RedirectsToResourceIndex;

    protected static string $resource = LensPackageResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
