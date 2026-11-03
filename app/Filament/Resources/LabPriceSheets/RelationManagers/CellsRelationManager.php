<?php

namespace App\Filament\Resources\LabPriceSheets\RelationManagers;

use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rules\Unique;

/** What the lab charges per pair for each (row, column) it sells. */
class CellsRelationManager extends RelationManager
{
    protected static string $relationship = 'cells';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('app.lab_sheets.cells');
    }

    public function form(Schema $schema): Schema
    {
        $sheet = $this->getOwnerRecord();

        return $schema
            ->columns(2)
            ->components([
                Select::make('lab_sheet_row_id')->label(__('app.lab_sheets.row'))
                    ->options(fn (): array => $sheet->rows()->orderBy('sort_order')->pluck('label', 'id')->all())
                    ->required()->searchable(),
                Select::make('lab_sheet_column_id')->label(__('app.lab_sheets.column'))
                    ->options(fn (): array => $sheet->columns()->orderBy('sort_order')->pluck('label', 'id')->all())
                    ->required()->searchable()
                    ->unique(
                        modifyRuleUsing: fn (Unique $rule, Get $get): Unique => $rule->where('lab_sheet_row_id', $get('lab_sheet_row_id')),
                        ignoreRecord: true,
                    ),
                TextInput::make('cost')->label(__('app.fields.cost'))
                    ->numeric()->integer()->minValue(0)->prefix('$')->required()
                    ->columnSpanFull()->helperText(__('app.lab_sheets.cell_help')),
                Toggle::make('is_preferred')->label(__('app.fields.is_preferred_lab'))
                    ->helperText(__('app.resources.lens_combination.preferred_help')),
                Toggle::make('is_active')->label(__('app.fields.active'))->default(true),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['row', 'column']))
            ->columns([
                TextColumn::make('row.label')->label(__('app.lab_sheets.row')),
                TextColumn::make('column.label')->label(__('app.lab_sheets.column')),
                TextColumn::make('cost')->label(__('app.fields.cost'))->money('COP'),
                IconColumn::make('is_preferred')->label(__('app.fields.is_preferred_lab'))->boolean(),
                IconColumn::make('is_active')->label(__('app.fields.active'))->boolean(),
            ])
            ->headerActions([CreateAction::make()])
            ->recordActions([EditAction::make(), DeleteAction::make()]);
    }
}
