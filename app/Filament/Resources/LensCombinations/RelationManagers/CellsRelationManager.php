<?php

namespace App\Filament\Resources\LensCombinations\RelationManagers;

use App\Filament\Resources\LabPriceSheets\LabPriceSheetResource;
use App\Filament\Resources\LensCombinations\Concerns\PrescriptionRangeFields;
use App\Models\LabSheetCell;
use Filament\Actions\Action;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/** Read-only: what each lab charges for this lens, as set on the labs' price sheets. */
class CellsRelationManager extends RelationManager
{
    use PrescriptionRangeFields;

    protected static string $relationship = 'cells';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('app.relations.lens_prices');
    }

    public function isReadOnly(): bool
    {
        return true;
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['sheet.supplier', 'row', 'column']))
            ->columns([
                TextColumn::make('sheet.supplier.name')->label(__('app.fields.laboratory')),
                TextColumn::make('sheet.name')->label(__('app.lab_sheets.sheet')),
                TextColumn::make('column.label')->label(__('app.lab_sheets.column')),
                TextColumn::make('row.label')->label(__('app.lab_sheets.row')),
                ...self::rangeColumns(),
                TextColumn::make('cost')->label(__('app.fields.cost'))->money('COP'),
                IconColumn::make('is_preferred')->label(__('app.fields.is_preferred_lab'))->boolean(),
                IconColumn::make('is_active')->label(__('app.fields.active'))->boolean(),
            ])
            ->recordActions([
                Action::make('edit_sheet')
                    ->label(__('app.lab_sheets.edit'))
                    ->url(fn (LabSheetCell $record): string => LabPriceSheetResource::getUrl('edit', ['record' => $record->lab_price_sheet_id])),
            ]);
    }
}
