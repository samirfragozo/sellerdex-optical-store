<?php

namespace App\Filament\Resources\LensMaterials\Pages;

use App\Filament\Concerns\RedirectsToResourceIndex;
use App\Filament\Resources\LensMaterials\LensMaterialResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditLensMaterial extends EditRecord
{
    use RedirectsToResourceIndex;

    protected static string $resource = LensMaterialResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
