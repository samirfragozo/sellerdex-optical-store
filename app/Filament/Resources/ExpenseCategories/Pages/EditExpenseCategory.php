<?php

namespace App\Filament\Resources\ExpenseCategories\Pages;

use App\Filament\Concerns\RedirectsToResourceIndex;
use App\Filament\Resources\ExpenseCategories\ExpenseCategoryResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditExpenseCategory extends EditRecord
{
    use RedirectsToResourceIndex;

    protected static string $resource = ExpenseCategoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->hidden(fn (): bool => $this->getRecord()->hasChildren()),
        ];
    }
}
