<?php

namespace App\Filament\Resources\CashCloses\Pages;

use App\Filament\Resources\CashCloses\CashCloseResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditCashClose extends EditRecord
{
    protected static string $resource = CashCloseResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
