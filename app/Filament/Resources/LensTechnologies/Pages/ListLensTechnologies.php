<?php

namespace App\Filament\Resources\LensTechnologies\Pages;

use App\Filament\Resources\LensTechnologies\LensTechnologyResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListLensTechnologies extends ListRecords
{
    protected static string $resource = LensTechnologyResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
