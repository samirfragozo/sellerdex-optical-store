<?php

namespace App\Filament\Resources\LensPackages;

use App\Filament\Clusters\Catalogo\CatalogoCluster;
use App\Filament\Resources\LensPackages\Pages\CreateLensPackage;
use App\Filament\Resources\LensPackages\Pages\EditLensPackage;
use App\Filament\Resources\LensPackages\Pages\ListLensPackages;
use App\Filament\Resources\LensPackages\Schemas\LensPackageForm;
use App\Filament\Resources\LensPackages\Tables\LensPackagesTable;
use App\Filament\Resources\Resource;
use App\Models\LensPackage;
use BackedEnum;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class LensPackageResource extends Resource
{
    protected static ?string $model = LensPackage::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $cluster = CatalogoCluster::class;

    protected static ?int $navigationSort = 14;

    public static function form(Schema $schema): Schema
    {
        return LensPackageForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return LensPackagesTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListLensPackages::route('/'),
            'create' => CreateLensPackage::route('/create'),
            'edit' => EditLensPackage::route('/{record}/edit'),
        ];
    }
}
