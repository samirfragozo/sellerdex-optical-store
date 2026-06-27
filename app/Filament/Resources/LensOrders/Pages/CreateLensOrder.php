<?php

namespace App\Filament\Resources\LensOrders\Pages;

use App\Filament\Concerns\RedirectsToResourceIndex;
use App\Filament\Resources\LensOrders\LensOrderResource;
use Filament\Resources\Pages\CreateRecord;

class CreateLensOrder extends CreateRecord
{
    use RedirectsToResourceIndex;

    protected static string $resource = LensOrderResource::class;
}
