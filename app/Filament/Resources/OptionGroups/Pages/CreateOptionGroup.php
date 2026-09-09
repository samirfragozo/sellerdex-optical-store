<?php

namespace App\Filament\Resources\OptionGroups\Pages;

use App\Filament\Concerns\RedirectsToResourceIndex;
use App\Filament\Resources\OptionGroups\OptionGroupResource;
use Filament\Resources\Pages\CreateRecord;

class CreateOptionGroup extends CreateRecord
{
    use RedirectsToResourceIndex;

    protected static string $resource = OptionGroupResource::class;
}
