<?php

namespace App\Filament\Resources\LensMaterials;

use App\Filament\Clusters\Catalogo\CatalogoCluster;
use App\Filament\Resources\LensMaterials\Pages\CreateLensMaterial;
use App\Filament\Resources\LensMaterials\Pages\EditLensMaterial;
use App\Filament\Resources\LensMaterials\Pages\ListLensMaterials;
use App\Filament\Resources\LensMaterials\Schemas\LensMaterialForm;
use App\Filament\Resources\LensMaterials\Tables\LensMaterialsTable;
use App\Filament\Resources\Resource;
use App\Models\LensMaterial;
use BackedEnum;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class LensMaterialResource extends Resource
{
    protected static ?string $model = LensMaterial::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $cluster = CatalogoCluster::class;

    protected static ?int $navigationSort = 12;

    public static function form(Schema $schema): Schema
    {
        return LensMaterialForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return LensMaterialsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListLensMaterials::route('/'),
            'create' => CreateLensMaterial::route('/create'),
            'edit' => EditLensMaterial::route('/{record}/edit'),
        ];
    }
}
