<?php

namespace App\Filament\Resources\LensCombinations\Pages;

use App\Filament\Concerns\RedirectsToResourceIndex;
use App\Filament\Resources\LensCombinations\LensCombinationResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditLensCombination extends EditRecord
{
    use RedirectsToResourceIndex;

    protected static string $resource = LensCombinationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
