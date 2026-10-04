<?php

namespace App\Filament\Forms;

use Filament\Forms\Components\TextInput;

/** The admin PIN a non-admin types to get an action approved; admins approve themselves and never see it. */
final class ApprovalPinInput
{
    public static function make(): TextInput
    {
        return TextInput::make('approval_pin')
            ->label(__('app.approval.pin'))
            ->helperText(__('app.approval.pin_help'))
            ->password()
            ->autocomplete('off')
            ->visible(fn (): bool => auth()->user()?->isAdmin() !== true)
            ->required(fn (): bool => auth()->user()?->isAdmin() !== true)
            ->dehydrated();
    }
}
