<?php

namespace App\Filament\Superadmin\Resources\CompanyResource\Pages;

use App\Actions\SeedCompanyDefaults;
use App\Filament\Superadmin\Resources\CompanyResource;
use Filament\Resources\Pages\CreateRecord;

class CreateCompany extends CreateRecord
{
    protected static string $resource = CompanyResource::class;

    protected function afterCreate(): void
    {
        (new SeedCompanyDefaults)->handle($this->record);
    }
}
