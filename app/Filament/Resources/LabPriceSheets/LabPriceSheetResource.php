<?php

namespace App\Filament\Resources\LabPriceSheets;

use App\Filament\Resources\LabPriceSheets\Pages\EditLabPriceSheet;
use App\Filament\Resources\LabPriceSheets\RelationManagers\CellsRelationManager;
use App\Filament\Resources\LabPriceSheets\RelationManagers\ColumnsRelationManager;
use App\Filament\Resources\LabPriceSheets\RelationManagers\RowsRelationManager;
use App\Filament\Resources\Resource;
use App\Filament\Resources\Suppliers\SupplierResource;
use App\Models\LabPriceSheet;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Model;

/** A lab's price sheet; reached from the supplier, so it has no navigation entry. */
class LabPriceSheetResource extends Resource
{
    protected static ?string $model = LabPriceSheet::class;

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->label(__('app.fields.name'))->required()->maxLength(255),
        ]);
    }

    public static function getIndexUrl(array $parameters = [], bool $isAbsolute = true, ?string $panel = null, ?Model $tenant = null, bool $shouldGuessMissingParameters = false): string
    {
        return SupplierResource::getUrl('index', $parameters, $isAbsolute, $panel, $tenant);
    }

    public static function getRelations(): array
    {
        return [ColumnsRelationManager::class, RowsRelationManager::class, CellsRelationManager::class];
    }

    public static function getPages(): array
    {
        return ['edit' => EditLabPriceSheet::route('/{record}/edit')];
    }
}
