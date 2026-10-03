<?php

namespace App\Filament\Resources\Products\Pages;

use App\Enums\StockMovementType;
use App\Filament\Concerns\RedirectsToResourceIndex;
use App\Filament\Resources\Products\ProductResource;
use App\Support\StockLedger;
use Filament\Resources\Pages\CreateRecord;

class CreateProduct extends CreateRecord
{
    use RedirectsToResourceIndex;

    protected static string $resource = ProductResource::class;

    /** The stock field isn't saved with the product; it enters through the ledger as the opening count. */
    protected function afterCreate(): void
    {
        $initialStock = (int) ($this->data['stock'] ?? 0);

        if ($initialStock !== 0) {
            StockLedger::record($this->record, StockMovementType::Initial, $initialStock);
        }
    }
}
