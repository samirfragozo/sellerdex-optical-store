<?php

namespace App\Filament\Resources\CashCloses\Pages;

use App\Filament\Resources\CashCloses\CashCloseResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListCashCloses extends ListRecords
{
    protected static string $resource = CashCloseResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
