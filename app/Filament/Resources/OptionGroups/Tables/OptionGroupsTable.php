<?php

namespace App\Filament\Resources\OptionGroups\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class OptionGroupsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label(__('app.fields.name'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('options_count')
                    ->counts('options')
                    ->label(__('app.fields.options')),
                IconColumn::make('is_required')
                    ->label(__('app.fields.is_required'))
                    ->boolean(),
                IconColumn::make('is_active')
                    ->label(__('app.fields.active'))
                    ->boolean(),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
