<?php

namespace App\Filament\Resources\LensPackages\Pages;

use App\Filament\Resources\LensPackages\LensPackageResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListLensPackages extends ListRecords
{
    protected static string $resource = LensPackageResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
