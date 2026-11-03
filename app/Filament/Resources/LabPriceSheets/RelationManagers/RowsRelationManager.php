<?php

namespace App\Filament\Resources\LabPriceSheets\RelationManagers;

use App\Filament\Resources\LensCombinations\Concerns\PrescriptionRangeFields;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/** The material and filter lines down the side of the lab's sheet, each with its sphere and cylinder range. */
class RowsRelationManager extends RelationManager
{
    use PrescriptionRangeFields;

    protected static string $relationship = 'rows';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('app.lab_sheets.rows');
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                TextInput::make('label')->label(__('app.fields.name'))->required()->maxLength(255)
                    ->columnSpanFull()->helperText(__('app.lab_sheets.row_help')),
                Select::make('lens_material_id')->label(__('app.resources.lens_material.label'))
                    ->relationship('lensMaterial', 'name')->required()->columnSpanFull(),
                ...self::sphereCylinderFields(),
                TextInput::make('sort_order')->label(__('app.fields.sort_order'))->numeric()->integer()->default(0),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('label')
            ->defaultSort('sort_order')
            ->columns([
                TextColumn::make('label')->label(__('app.fields.name')),
                TextColumn::make('lensMaterial.name')->label(__('app.resources.lens_material.label')),
                ...array_slice(self::rangeColumns(), 0, 2),
            ])
            ->headerActions([CreateAction::make()])
            ->recordActions([EditAction::make(), DeleteAction::make()]);
    }
}
