<?php

namespace App\Filament\Resources\LensCombinations\RelationManagers;

use App\Filament\Resources\LensCombinations\Concerns\PrescriptionRangeFields;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/** What the shop charges per pair of this lens, by prescription range. */
class PriceBandsRelationManager extends RelationManager
{
    use PrescriptionRangeFields;

    protected static string $relationship = 'priceBands';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('app.relations.lens_price_bands');
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                ...self::rangeFields(),
                TextInput::make('price')
                    ->label(__('app.fields.price'))
                    ->numeric()->integer()->minValue(1)->prefix('$')->required()
                    ->columnSpanFull()
                    ->helperText(__('app.resources.lens_combination.price_bands_help')),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                ...self::rangeColumns(),
                TextColumn::make('price')->label(__('app.fields.price'))->money('COP'),
            ])
            ->headerActions([CreateAction::make()])
            ->recordActions([EditAction::make(), DeleteAction::make()]);
    }
}
