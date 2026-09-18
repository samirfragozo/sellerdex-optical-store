<?php

namespace App\Filament\Resources\LensTreatments\Pages;

use App\Filament\Resources\LensTreatments\LensTreatmentResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListLensTreatments extends ListRecords
{
    protected static string $resource = LensTreatmentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
