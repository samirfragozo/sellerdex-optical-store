<?php

namespace App\Filament\Resources\Prescriptions\Tables;

use App\Models\Prescription;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

class PrescriptionsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('customer.name')
                    ->label(__('app.fields.customer'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('exam_date')
                    ->label(__('app.fields.date'))
                    ->date()
                    ->sortable()
                    ->searchable(),
                TextColumn::make('lens_type')
                    ->label(__('app.fields.lens_type'))
                    ->badge(),
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
                Action::make('printFormula')
                    ->label(__('app.documents.print_formula'))
                    ->icon('heroicon-o-printer')
                    ->url(fn (Prescription $record) => route('documents.formula', $record))
                    ->openUrlInNewTab(),
                Action::make('downloadFormula')
                    ->label(__('app.documents.download_formula'))
                    ->icon('heroicon-o-arrow-down-tray')
                    ->url(fn (Prescription $record) => route('documents.formula.pdf', $record)),
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
