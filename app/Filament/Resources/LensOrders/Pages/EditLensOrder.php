<?php

namespace App\Filament\Resources\LensOrders\Pages;

use App\Filament\Concerns\RedirectsToResourceIndex;
use App\Filament\Resources\LensOrders\LensOrderResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditLensOrder extends EditRecord
{
    use RedirectsToResourceIndex;

    protected static string $resource = LensOrderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
