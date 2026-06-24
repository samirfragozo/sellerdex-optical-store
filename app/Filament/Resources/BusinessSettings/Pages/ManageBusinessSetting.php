<?php

namespace App\Filament\Resources\BusinessSettings\Pages;

use App\Filament\Resources\BusinessSettings\BusinessSettingResource;
use App\Models\BusinessSetting;
use Filament\Resources\Pages\EditRecord;

class ManageBusinessSetting extends EditRecord
{
    protected static string $resource = BusinessSettingResource::class;

    /** Always edit the single business-settings row, regardless of route params. */
    public function mount(int|string|null $record = null): void
    {
        parent::mount(BusinessSetting::current()->getKey());
    }

    public function getBreadcrumb(): string
    {
        return __('app.business.title');
    }

    public function getTitle(): string
    {
        return __('app.business.title');
    }

    /** No delete for the singleton. */
    protected function getHeaderActions(): array
    {
        return [];
    }

    protected function getRedirectUrl(): ?string
    {
        return null;
    }
}
