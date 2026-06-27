<?php

namespace App\Filament\Resources\PurchaseOrders\Pages;

use App\Filament\Concerns\RedirectsToResourceIndex;
use App\Filament\Resources\PurchaseOrders\PurchaseOrderResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditPurchaseOrder extends EditRecord
{
    use RedirectsToResourceIndex;

    protected static string $resource = PurchaseOrderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
