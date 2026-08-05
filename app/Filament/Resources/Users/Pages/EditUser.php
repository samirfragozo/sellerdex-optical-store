<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Concerns\RedirectsToResourceIndex;
use App\Filament\Resources\Users\UserResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Arr;

class EditUser extends EditRecord
{
    use RedirectsToResourceIndex;

    protected static string $resource = UserResource::class;

    protected string $role;

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['role'] = $this->record->roles->pluck('name')->first();

        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $this->role = Arr::pull($data, 'role');

        return $data;
    }

    protected function afterSave(): void
    {
        $this->record->syncRoles([$this->role]);
    }

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
