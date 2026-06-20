<?php

namespace App\Filament\Resources\Sales\RelationManagers;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class PaymentsRelationManager extends RelationManager
{
    protected static string $relationship = 'payments';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('payment_method_id')
                    ->label(__('app.fields.payment_method'))
                    ->relationship('paymentMethod', 'name')
                    ->required(),
                TextInput::make('amount')
                    ->label(__('app.fields.amount'))
                    ->numeric()
                    ->prefix('$')
                    ->required(),
                DatePicker::make('paid_at')
                    ->label(__('app.fields.paid_at'))
                    ->default(now())
                    ->required(),
                TextInput::make('reference')
                    ->label(__('app.fields.reference')),
                Textarea::make('notes')
                    ->label(__('app.fields.notes'))
                    ->columnSpanFull(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('amount')
            ->columns([
                TextColumn::make('paymentMethod.name')
                    ->label(__('app.fields.payment_method'))
                    ->searchable(),
                TextColumn::make('amount')
                    ->label(__('app.fields.amount'))
                    ->money('COP')
                    ->sortable(),
                TextColumn::make('paid_at')
                    ->label(__('app.fields.paid_at'))
                    ->date()
                    ->sortable(),
                TextColumn::make('reference')
                    ->label(__('app.fields.reference'))
                    ->searchable(),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                CreateAction::make()
                    ->mutateFormDataUsing(function (array $data): array {
                        $data['received_by'] ??= auth()->id();

                        return $data;
                    }),
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
