<?php

namespace App\Filament\Resources\LensTechnologies;

use App\Filament\Clusters\Catalogo\CatalogoCluster;
use App\Filament\Resources\LensTechnologies\Pages\CreateLensTechnology;
use App\Filament\Resources\LensTechnologies\Pages\EditLensTechnology;
use App\Filament\Resources\LensTechnologies\Pages\ListLensTechnologies;
use App\Filament\Resources\LensTechnologies\Schemas\LensTechnologyForm;
use App\Filament\Resources\LensTechnologies\Tables\LensTechnologiesTable;
use App\Filament\Resources\Resource;
use App\Models\LensTechnology;
use BackedEnum;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class LensTechnologyResource extends Resource
{
    protected static ?string $model = LensTechnology::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $cluster = CatalogoCluster::class;

    protected static ?int $navigationSort = 11;

    public static function form(Schema $schema): Schema
    {
        return LensTechnologyForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return LensTechnologiesTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListLensTechnologies::route('/'),
            'create' => CreateLensTechnology::route('/create'),
            'edit' => EditLensTechnology::route('/{record}/edit'),
        ];
    }
}
