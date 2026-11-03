<?php

namespace App\Filament\Resources\Suppliers\RelationManagers;

use App\Actions\CopyLabPriceSheet;
use App\Filament\Resources\LabPriceSheets\LabPriceSheetResource;
use App\Models\LabPriceSheet;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/** A laboratory's price sheets; the grid itself is edited on the sheet page. */
class PriceSheetsRelationManager extends RelationManager
{
    protected static string $relationship = 'priceSheets';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('app.lab_sheets.title');
    }

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return $ownerRecord->is_laboratory && parent::canViewForRecord($ownerRecord, $pageClass);
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->label(__('app.fields.name'))->required()->maxLength(255),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->columns([TextColumn::make('name')->label(__('app.fields.name'))])
            ->headerActions([
                CreateAction::make(),
                Action::make('copyFrom')
                    ->label(__('app.lab_sheets.copy_from'))
                    ->color('gray')
                    ->modalDescription(__('app.lab_sheets.copy_help'))
                    ->schema([
                        Select::make('source_id')
                            ->label(__('app.lab_sheets.copy_source'))
                            ->options(fn (): array => LabPriceSheet::query()->with('supplier')->get()
                                ->mapWithKeys(fn (LabPriceSheet $sheet): array => [$sheet->id => "{$sheet->supplier?->name} · {$sheet->name}"])->all())
                            ->searchable()
                            ->required()
                            ->live()
                            ->afterStateUpdated(fn (mixed $state, Set $set) => $set('name', LabPriceSheet::find($state)?->name)),
                        TextInput::make('name')->label(__('app.fields.name'))->required()->maxLength(255),
                    ])
                    ->action(function (array $data) {
                        $sheet = app(CopyLabPriceSheet::class)->handle(LabPriceSheet::findOrFail($data['source_id']), $this->getOwnerRecord(), $data['name']);

                        return redirect(LabPriceSheetResource::getUrl('edit', ['record' => $sheet]));
                    }),
            ])
            ->recordActions([
                Action::make('edit_sheet')
                    ->label(__('app.lab_sheets.edit'))
                    ->url(fn (LabPriceSheet $record): string => LabPriceSheetResource::getUrl('edit', ['record' => $record])),
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }
}
