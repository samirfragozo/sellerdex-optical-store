<?php

namespace App\Filament\Resources\LensCombinations\Pages;

use App\Filament\Resources\LensCombinations\LensCombinationResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListLensCombinations extends ListRecords
{
    protected static string $resource = LensCombinationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
