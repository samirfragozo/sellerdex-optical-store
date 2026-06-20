<?php

namespace App\Filament\Resources\Customers\Tables;

use App\Enums\DocumentType;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

class CustomersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label(__('app.fields.first_name'))
                    ->searchable(),
                TextColumn::make('last_name')
                    ->label(__('app.fields.last_name'))
                    ->searchable(),
                TextColumn::make('document_type')
                    ->label(__('app.fields.document_type'))
                    ->badge()
                    ->formatStateUsing(fn (DocumentType $state): string => $state->label()),
                TextColumn::make('id_number')
                    ->label(__('app.fields.id_number'))
                    ->searchable(),
                TextColumn::make('phone')
                    ->label(__('app.fields.phone'))
                    ->searchable(),
                TextColumn::make('address')
                    ->label(__('app.fields.address'))
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('city')
                    ->label(__('app.fields.city')),
                TextColumn::make('birth_date')
                    ->label(__('app.fields.birth_date'))
                    ->date()
                    ->sortable(),
                TextColumn::make('email')
                    ->label(__('app.fields.email'))
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('deleted_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                TrashedFilter::make(),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]),
            ]);
    }
}
