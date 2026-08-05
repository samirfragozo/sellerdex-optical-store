<?php

namespace App\Filament\Resources\Users\Tables;

use App\Models\User;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;

class UsersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label(__('app.fields.name'))
                    ->searchable(),
                TextColumn::make('email')
                    ->label(__('app.fields.email'))
                    ->searchable(),
                TextColumn::make('roles.name')
                    ->label(__('app.fields.role'))
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        User::ROLE_ADMIN => __('app.fields.role_admin'),
                        User::ROLE_SELLER => __('app.fields.role_seller'),
                        default => $state,
                    }),
                IconColumn::make('is_active')
                    ->label(__('app.fields.active'))
                    ->boolean(),
            ])
            ->recordActions([
                EditAction::make(),
                Action::make('toggle_active')
                    ->label(fn (User $record) => $record->is_active
                        ? __('app.users.suspend')
                        : __('app.users.activate'))
                    ->icon(fn (User $record) => $record->is_active ? 'heroicon-o-x-circle' : 'heroicon-o-check-circle')
                    ->color(fn (User $record) => $record->is_active ? 'danger' : 'success')
                    ->visible(fn (User $record): bool => $record->id !== Auth::id())
                    ->action(fn (User $record) => $record->update(['is_active' => ! $record->is_active])),
            ]);
    }
}
