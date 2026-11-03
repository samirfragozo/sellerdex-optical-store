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

/** The designs printed across the top of the lab's sheet. */
class ColumnsRelationManager extends RelationManager
{
    use PrescriptionRangeFields;

    protected static string $relationship = 'columns';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('app.lab_sheets.columns');
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                TextInput::make('label')->label(__('app.fields.name'))->required()->maxLength(255)
                    ->columnSpanFull()->helperText(__('app.lab_sheets.column_help')),
                Select::make('lens_type_id')->label(__('app.resources.lens_type.label'))
                    ->relationship('lensType', 'name')->required(),
                Select::make('lens_technology_id')->label(__('app.resources.lens_technology.label'))
                    ->relationship('lensTechnology', 'name')->required(),
                ...self::additionFields(),
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
                TextColumn::make('lensType.name')->label(__('app.resources.lens_type.label')),
                TextColumn::make('lensTechnology.name')->label(__('app.resources.lens_technology.label')),
                self::rangeColumns()[2],
            ])
            ->headerActions([CreateAction::make()])
            ->recordActions([EditAction::make(), DeleteAction::make()]);
    }
}
