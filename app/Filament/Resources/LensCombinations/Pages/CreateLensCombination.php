<?php

namespace App\Filament\Resources\LensCombinations\Pages;

use App\Filament\Resources\LensCombinations\LensCombinationResource;
use Filament\Resources\Pages\CreateRecord;

class CreateLensCombination extends CreateRecord
{
    protected static string $resource = LensCombinationResource::class;

    /** A new combination is unsellable until priced: land where the prices relation manager lives. */
    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('edit', ['record' => $this->getRecord()]);
    }
}
