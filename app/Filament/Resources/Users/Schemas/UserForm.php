<?php

namespace App\Filament\Resources\Users\Schemas;

use App\Models\User;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

class UserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label(__('app.fields.name'))
                    ->required()
                    ->maxLength(255),
                TextInput::make('email')
                    ->label(__('app.fields.email'))
                    ->email()
                    ->required()
                    ->maxLength(255)
                    ->unique(ignoreRecord: true),
                Select::make('role')
                    ->label(__('app.fields.role'))
                    ->options([
                        User::ROLE_ADMIN => __('app.fields.role_admin'),
                        User::ROLE_SELLER => __('app.fields.role_seller'),
                    ])
                    ->live()
                    ->required(),
                TextInput::make('approval_pin')
                    ->label(__('app.approval.pin'))
                    ->helperText(__('app.approval.pin_setup_help'))
                    ->password()
                    ->revealable()
                    ->autocomplete('new-password')
                    ->regex('/^\d{4,6}$/')
                    ->visible(fn (Get $get): bool => $get('role') === User::ROLE_ADMIN)
                    // Blank keeps the current PIN.
                    ->dehydrated(fn (?string $state): bool => filled($state)),
            ]);
    }
}
