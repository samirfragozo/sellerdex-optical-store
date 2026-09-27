<?php

namespace App\Filament\Resources\Taxes\Tables;

use App\Enums\TaxTreatment;
use App\Models\Tax;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class TaxesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label(__('app.fields.name'))
                    ->searchable(),
                TextColumn::make('rate')
                    ->label(__('app.fields.rate'))
                    ->suffix('%')
                    ->sortable(),
                TextColumn::make('treatment')
                    ->label(__('app.fields.treatment'))
                    ->formatStateUsing(fn (TaxTreatment $state): string => $state->label()),
                IconColumn::make('is_active')
                    ->label(__('app.fields.active'))
                    ->boolean(),
                TextColumn::make('is_system')
                    ->label(__('app.fields.is_system'))
                    ->getStateUsing(fn (Tax $record): ?string => $record->is_system ? __('app.fields.is_system') : null)
                    ->badge(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make()
                    ->hidden(fn (Tax $record): bool => $record->is_system || $record->isInUse()),
            ]);
    }
}
