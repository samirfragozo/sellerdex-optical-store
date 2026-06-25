<?php

namespace App\Filament\Resources\ProductCategories\Pages;

use App\Filament\Concerns\RedirectsToResourceIndex;
use App\Filament\Resources\ProductCategories\ProductCategoryResource;
use Filament\Resources\Pages\CreateRecord;

class CreateProductCategory extends CreateRecord
{
    use RedirectsToResourceIndex;

    protected static string $resource = ProductCategoryResource::class;
}
