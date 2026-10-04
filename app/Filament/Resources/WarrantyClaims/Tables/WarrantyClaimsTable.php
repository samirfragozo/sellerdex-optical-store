<?php

namespace App\Filament\Resources\WarrantyClaims\Tables;

use App\Enums\WarrantyClaimStatus;
use App\Models\WarrantyClaim;
use Filament\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class WarrantyClaimsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')->label('#')->sortable(),
                TextColumn::make('saleItem.sale.number')->label(__('app.fields.number')),
                TextColumn::make('saleItem.description')->label(__('app.fields.item'))->searchable(),
                TextColumn::make('saleItem.sale.customer.full_name')->label(__('app.fields.customer')),
                TextColumn::make('type')->label(__('app.fields.type'))->badge(),
                TextColumn::make('status')->label(__('app.fields.status'))->badge(),
                TextColumn::make('received_at')->label(__('app.fields.received_at'))->date('d/m/Y')->sortable(),
                TextColumn::make('resolution')->label(__('app.warranty.fields.resolution'))->badge()->placeholder('—'),
            ])
            ->defaultSort('received_at', 'desc')
            ->filters([
                SelectFilter::make('status')->label(__('app.fields.status'))->options(WarrantyClaimStatus::options()),
            ])
            ->recordActions([
                Action::make('startReview')
                    ->label(__('app.warranty.actions.start_review'))
                    ->visible(fn (WarrantyClaim $record): bool => $record->status === WarrantyClaimStatus::Received)
                    ->requiresConfirmation()
                    ->action(fn (WarrantyClaim $record) => $record->advance(WarrantyClaimStatus::InReview)),
                Action::make('sendToSupplier')
                    ->label(__('app.warranty.actions.send_to_supplier'))
                    ->visible(fn (WarrantyClaim $record): bool => $record->status === WarrantyClaimStatus::InReview)
                    ->requiresConfirmation()
                    ->action(fn (WarrantyClaim $record) => $record->advance(WarrantyClaimStatus::AtSupplier)),
            ]);
    }
}
