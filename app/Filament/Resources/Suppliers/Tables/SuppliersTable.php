<?php

namespace App\Filament\Resources\Suppliers\Tables;

use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class SuppliersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label(__('app.fields.name'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('contact_name')
                    ->label(__('app.fields.contact_name'))
                    ->searchable(),
                TextColumn::make('phone')
                    ->label(__('app.fields.phone')),
                IconColumn::make('is_laboratory')
                    ->label(__('app.fields.is_laboratory'))
                    ->boolean(),
                IconColumn::make('is_active')
                    ->label(__('app.fields.active'))
                    ->boolean(),
            ])
            ->recordActions([
                EditAction::make(),
            ]);
    }
}
