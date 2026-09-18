<?php

namespace App\Filament\Resources\LensTreatments;

use App\Filament\Clusters\Catalogo\CatalogoCluster;
use App\Filament\Resources\LensTreatments\Pages\CreateLensTreatment;
use App\Filament\Resources\LensTreatments\Pages\EditLensTreatment;
use App\Filament\Resources\LensTreatments\Pages\ListLensTreatments;
use App\Filament\Resources\LensTreatments\Schemas\LensTreatmentForm;
use App\Filament\Resources\LensTreatments\Tables\LensTreatmentsTable;
use App\Filament\Resources\Resource;
use App\Models\LensTreatment;
use BackedEnum;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class LensTreatmentResource extends Resource
{
    protected static ?string $model = LensTreatment::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $cluster = CatalogoCluster::class;

    protected static ?int $navigationSort = 13;

    public static function form(Schema $schema): Schema
    {
        return LensTreatmentForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return LensTreatmentsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListLensTreatments::route('/'),
            'create' => CreateLensTreatment::route('/create'),
            'edit' => EditLensTreatment::route('/{record}/edit'),
        ];
    }
}
