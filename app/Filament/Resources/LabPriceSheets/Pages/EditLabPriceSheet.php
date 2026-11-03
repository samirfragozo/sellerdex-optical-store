<?php

namespace App\Filament\Resources\LabPriceSheets\Pages;

use App\Filament\Resources\LabPriceSheets\LabPriceSheetResource;
use App\Filament\Resources\Suppliers\SupplierResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditLabPriceSheet extends EditRecord
{
    protected static string $resource = LabPriceSheetResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()->successRedirectUrl(fn (): string => SupplierResource::getUrl('edit', ['record' => $this->getRecord()->supplier_id])),
        ];
    }
}
