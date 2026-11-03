<?php

namespace App\Filament\Resources\LabPriceSheets;

use App\Filament\Resources\LabPriceSheets\Pages\ManageLabPriceSheet;
use App\Filament\Resources\Resource;
use App\Filament\Resources\Suppliers\SupplierResource;
use App\Models\LabPriceSheet;
use Illuminate\Database\Eloquent\Model;

/** A lab's price sheet; reached from the supplier, so it has no navigation entry. Edited as one grid. */
class LabPriceSheetResource extends Resource
{
    protected static ?string $model = LabPriceSheet::class;

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $recordTitleAttribute = 'name';

    public static function getIndexUrl(array $parameters = [], bool $isAbsolute = true, ?string $panel = null, ?Model $tenant = null, bool $shouldGuessMissingParameters = false): string
    {
        return SupplierResource::getUrl('index', $parameters, $isAbsolute, $panel, $tenant);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return ['edit' => ManageLabPriceSheet::route('/{record}/edit')];
    }
}
