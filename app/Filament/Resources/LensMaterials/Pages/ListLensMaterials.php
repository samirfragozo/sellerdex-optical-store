<?php

namespace App\Filament\Resources\LensMaterials\Pages;

use App\Filament\Resources\LensMaterials\LensMaterialResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListLensMaterials extends ListRecords
{
    protected static string $resource = LensMaterialResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
