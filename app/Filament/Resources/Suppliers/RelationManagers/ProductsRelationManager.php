<?php

namespace App\Filament\Resources\Suppliers\RelationManagers;

use Filament\Actions\AttachAction;
use Filament\Actions\DetachAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class ProductsRelationManager extends RelationManager
{
    protected static string $relationship = 'products';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('app.relations.products');
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('supplier_cost')
                ->label(__('app.fields.supplier_cost'))
                ->numeric()
                ->required(),
            TextInput::make('lead_time_days')
                ->label(__('app.fields.lead_time_days'))
                ->numeric()
                ->minValue(0),
            TextInput::make('supplier_sku')
                ->label(__('app.fields.supplier_sku'))
                ->maxLength(255),
            Toggle::make('is_preferred')
                ->label(__('app.fields.preferred')),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->columns([
                TextColumn::make('name')
                    ->label(__('app.fields.name'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('sku')
                    ->label(__('app.fields.sku'))
                    ->searchable(),
                TextColumn::make('pivot.supplier_sku')
                    ->label(__('app.fields.supplier_sku'))
                    ->searchable(),
                TextColumn::make('pivot.supplier_cost')
                    ->label(__('app.fields.supplier_cost'))
                    ->money('COP')
                    ->visible(fn () => auth()->user()?->isAdmin() === true),
                TextColumn::make('pivot.lead_time_days')
                    ->label(__('app.fields.lead_time_days')),
                IconColumn::make('pivot.is_preferred')
                    ->label(__('app.fields.preferred'))
                    ->boolean(),
            ])
            ->headerActions([
                AttachAction::make()
                    ->preloadRecordSelect()
                    ->schema(fn (AttachAction $action): array => [
                        $action->getRecordSelect(),
                        TextInput::make('supplier_cost')
                            ->label(__('app.fields.supplier_cost'))
                            ->numeric()
                            ->required(),
                        TextInput::make('lead_time_days')
                            ->label(__('app.fields.lead_time_days'))
                            ->numeric()
                            ->minValue(0),
                        TextInput::make('supplier_sku')
                            ->label(__('app.fields.supplier_sku'))
                            ->maxLength(255),
                        Toggle::make('is_preferred')
                            ->label(__('app.fields.preferred')),
                    ]),
            ])
            ->recordActions([
                EditAction::make(),
                DetachAction::make(),
            ]);
    }
}
