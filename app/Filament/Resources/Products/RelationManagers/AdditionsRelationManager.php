<?php

namespace App\Filament\Resources\Products\RelationManagers;

use App\Models\Product;
use Filament\Actions\AttachAction;
use Filament\Actions\DetachAction;
use Filament\Actions\EditAction;
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

class AdditionsRelationManager extends RelationManager
{
    protected static string $relationship = 'additions';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('app.relations.additions');
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('price')
                ->label(__('app.fields.price'))
                ->numeric()
                ->required()
                ->default(0)
                ->minValue(fn (?Model $record): ?int => $record instanceof Product ? -$record->price : null),
            TextInput::make('quantity')
                ->label(__('app.fields.quantity'))
                ->numeric()
                ->required()
                ->default(1)
                ->minValue(1),
            Toggle::make('is_active')
                ->label(__('app.fields.active'))
                ->default(true)
                ->required(),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->inverseRelationship('additionOf')
            ->columns([
                TextColumn::make('name')
                    ->label(__('app.fields.name'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('sku')
                    ->label(__('app.fields.sku'))
                    ->searchable(),
                TextColumn::make('pivot.price')
                    ->label(__('app.fields.price'))
                    ->money('COP'),
                TextColumn::make('pivot.quantity')
                    ->label(__('app.fields.quantity')),
                IconColumn::make('pivot.is_active')
                    ->label(__('app.fields.active'))
                    ->boolean(),
            ])
            ->headerActions([
                AttachAction::make()
                    ->recordSelectOptionsQuery(fn (Builder $query): Builder => $query
                        ->where('products.id', '!=', $this->getOwnerRecord()->id)
                        ->whereNotIn('products.id', $this->getOwnerRecord()->additions()->pluck('products.id')))
                    ->preloadRecordSelect()
                    ->schema(fn (AttachAction $action): array => [
                        $action->getRecordSelect(),
                        TextInput::make('price')
                            ->label(__('app.fields.price'))
                            ->numeric()
                            ->required()
                            ->default(0)
                            ->rule(function (Get $get): \Closure {
                                return function (string $attribute, $value, \Closure $fail) use ($get): void {
                                    $additionId = $get('recordId');
                                    $addition = $additionId ? Product::find($additionId) : null;

                                    if ($addition instanceof Product && ($addition->price + (int) $value) < 0) {
                                        $fail(__('app.validation.addition_price_floor'));
                                    }
                                };
                            }),
                        TextInput::make('quantity')
                            ->label(__('app.fields.quantity'))
                            ->numeric()
                            ->required()
                            ->default(1)
                            ->minValue(1),
                        Toggle::make('is_active')
                            ->label(__('app.fields.active'))
                            ->default(true)
                            ->required(),
                    ]),
            ])
            ->recordActions([
                EditAction::make(),
                DetachAction::make(),
            ]);
    }
}
