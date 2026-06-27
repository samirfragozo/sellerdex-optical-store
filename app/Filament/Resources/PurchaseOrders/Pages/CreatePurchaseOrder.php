<?php

namespace App\Filament\Resources\PurchaseOrders\Pages;

use App\Filament\Concerns\RedirectsToResourceIndex;
use App\Filament\Resources\PurchaseOrders\PurchaseOrderResource;
use Filament\Resources\Pages\CreateRecord;

class CreatePurchaseOrder extends CreateRecord
{
    use RedirectsToResourceIndex;

    protected static string $resource = PurchaseOrderResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['created_by'] ??= auth()->id();

        return $data;
    }
}
