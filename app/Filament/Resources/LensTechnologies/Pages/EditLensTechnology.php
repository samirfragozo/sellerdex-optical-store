<?php

namespace App\Filament\Resources\LensTechnologies\Pages;

use App\Filament\Concerns\RedirectsToResourceIndex;
use App\Filament\Resources\LensTechnologies\LensTechnologyResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditLensTechnology extends EditRecord
{
    use RedirectsToResourceIndex;

    protected static string $resource = LensTechnologyResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
