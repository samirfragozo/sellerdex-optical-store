<?php

namespace App\Filament\Resources\OptionGroups;

use App\Filament\Clusters\Catalogo\CatalogoCluster;
use App\Filament\Resources\OptionGroups\Pages\CreateOptionGroup;
use App\Filament\Resources\OptionGroups\Pages\EditOptionGroup;
use App\Filament\Resources\OptionGroups\Pages\ListOptionGroups;
use App\Filament\Resources\OptionGroups\Schemas\OptionGroupForm;
use App\Filament\Resources\OptionGroups\Tables\OptionGroupsTable;
use App\Filament\Resources\Resource;
use App\Models\OptionGroup;
use BackedEnum;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class OptionGroupResource extends Resource
{
    protected static ?string $model = OptionGroup::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedAdjustmentsHorizontal;

    protected static ?string $cluster = CatalogoCluster::class;

    protected static ?int $navigationSort = 2;

    public static function form(Schema $schema): Schema
    {
        return OptionGroupForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return OptionGroupsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListOptionGroups::route('/'),
            'create' => CreateOptionGroup::route('/create'),
            'edit' => EditOptionGroup::route('/{record}/edit'),
        ];
    }
}
