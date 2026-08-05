<?php

namespace App\Filament\Resources\Users\Schemas;

use App\Models\User;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class UserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label(__('app.fields.name'))
                    ->required(),
                TextInput::make('email')
                    ->label(__('app.fields.email'))
                    ->email()
                    ->required()
                    ->unique(ignoreRecord: true),
                Select::make('role')
                    ->label(__('app.fields.role'))
                    ->options([
                        User::ROLE_ADMIN => __('app.fields.role_admin'),
                        User::ROLE_SELLER => __('app.fields.role_seller'),
                    ])
                    ->required(),
            ]);
    }
}
