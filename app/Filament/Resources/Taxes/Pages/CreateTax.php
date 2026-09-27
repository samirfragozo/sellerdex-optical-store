<?php

namespace App\Filament\Resources\Taxes\Pages;

use App\Filament\Concerns\RedirectsToResourceIndex;
use App\Filament\Resources\Taxes\TaxResource;
use Filament\Resources\Pages\CreateRecord;

class CreateTax extends CreateRecord
{
    use RedirectsToResourceIndex;

    protected static string $resource = TaxResource::class;
}
