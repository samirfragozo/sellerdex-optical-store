<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Concerns\RedirectsToResourceIndex;
use App\Filament\Resources\Users\UserResource;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

class CreateUser extends CreateRecord
{
    use RedirectsToResourceIndex;

    protected static string $resource = UserResource::class;

    protected string $role;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $this->role = Arr::pull($data, 'role');
        $data['password'] = Str::random(40);
        $data['company_id'] = Auth::user()->company_id;

        return $data;
    }

    protected function afterCreate(): void
    {
        $this->record->syncRoles([$this->role]);

        Password::sendResetLink(['email' => $this->record->email]);

        Notification::make()
            ->success()
            ->title(__('app.users.invite_sent'))
            ->send();
    }
}
