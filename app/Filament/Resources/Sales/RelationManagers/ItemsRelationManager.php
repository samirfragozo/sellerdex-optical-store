<?php

namespace App\Filament\Resources\Sales\RelationManagers;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class ItemsRelationManager extends RelationManager
{
    protected static string $relationship = 'items';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('app.fields.items');
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('product_id')
                    ->label(__('app.fields.product'))
                    ->relationship('product', 'name')
                    ->searchable(),
                TextInput::make('description')
                    ->label(__('app.fields.description'))
                    ->required(),
                TextInput::make('quantity')
                    ->label(__('app.fields.quantity'))
                    ->numeric()
                    ->default(1)
                    ->required(),
                TextInput::make('unit_price')
                    ->label(__('app.fields.unit_price'))
                    ->numeric()
                    ->default(0)
                    ->prefix('$')
                    ->required(),
                TextInput::make('unit_cost')
                    ->label(__('app.fields.unit_cost'))
                    ->numeric()
                    ->default(0)
                    ->prefix('$')
                    ->visible(fn (): bool => auth()->user()?->isAdmin() === true),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('description')
                    ->label(__('app.fields.description'))
                    ->wrap()
                    ->searchable(),
                TextColumn::make('quantity')
                    ->label(__('app.fields.quantity'))
                    ->alignEnd(),
                TextColumn::make('unit_price')
                    ->label(__('app.fields.unit_price'))
                    ->money('COP')
                    ->alignEnd(),
                TextColumn::make('unit_cost')
                    ->label(__('app.fields.unit_cost'))
                    ->money('COP')
                    ->alignEnd()
                    ->visible(fn (): bool => auth()->user()?->isAdmin() === true),
                TextColumn::make('line_total')
                    ->label(__('app.fields.total'))
                    ->money('COP')
                    ->alignEnd(),
            ])
            ->headerActions([
                CreateAction::make(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
