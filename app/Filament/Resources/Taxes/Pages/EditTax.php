<?php

namespace App\Filament\Resources\Taxes\Pages;

use App\Filament\Concerns\RedirectsToResourceIndex;
use App\Filament\Resources\Taxes\TaxResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditTax extends EditRecord
{
    use RedirectsToResourceIndex;

    protected static string $resource = TaxResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->hidden(fn (): bool => $this->getRecord()->is_system || $this->getRecord()->isInUse()),
        ];
    }
}
