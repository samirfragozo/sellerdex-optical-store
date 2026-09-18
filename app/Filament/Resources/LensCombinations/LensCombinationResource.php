<?php

namespace App\Filament\Resources\LensCombinations;

use App\Filament\Clusters\Catalogo\CatalogoCluster;
use App\Filament\Resources\LensCombinations\Pages\CreateLensCombination;
use App\Filament\Resources\LensCombinations\Pages\EditLensCombination;
use App\Filament\Resources\LensCombinations\Pages\ListLensCombinations;
use App\Filament\Resources\LensCombinations\Schemas\LensCombinationForm;
use App\Filament\Resources\LensCombinations\Tables\LensCombinationsTable;
use App\Filament\Resources\Resource;
use App\Models\LensCombination;
use BackedEnum;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class LensCombinationResource extends Resource
{
    protected static ?string $model = LensCombination::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTableCells;

    protected static ?string $cluster = CatalogoCluster::class;

    protected static ?int $navigationSort = 15;

    public static function form(Schema $schema): Schema
    {
        return LensCombinationForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return LensCombinationsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListLensCombinations::route('/'),
            'create' => CreateLensCombination::route('/create'),
            'edit' => EditLensCombination::route('/{record}/edit'),
        ];
    }
}
