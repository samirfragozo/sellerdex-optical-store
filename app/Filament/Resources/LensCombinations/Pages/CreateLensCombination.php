<?php

namespace App\Filament\Resources\LensCombinations\Pages;

use App\Filament\Concerns\RedirectsToResourceIndex;
use App\Filament\Resources\LensCombinations\LensCombinationResource;
use Filament\Resources\Pages\CreateRecord;

class CreateLensCombination extends CreateRecord
{
    use RedirectsToResourceIndex;

    protected static string $resource = LensCombinationResource::class;
}
