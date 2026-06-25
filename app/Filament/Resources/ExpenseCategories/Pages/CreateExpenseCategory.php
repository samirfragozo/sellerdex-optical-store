<?php

namespace App\Filament\Resources\ExpenseCategories\Pages;

use App\Filament\Concerns\RedirectsToResourceIndex;
use App\Filament\Resources\ExpenseCategories\ExpenseCategoryResource;
use Filament\Resources\Pages\CreateRecord;

class CreateExpenseCategory extends CreateRecord
{
    use RedirectsToResourceIndex;

    protected static string $resource = ExpenseCategoryResource::class;
}
