<?php

namespace App\Filament\Resources\Sales\RelationManagers;

use App\Enums\SaleStatus;
use App\Models\Product;
use App\Models\SaleItem;
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
            ->description(fn (): ?string => $this->isLocked() ? __('app.sale_return.items_locked') : null)
            ->columns([
                TextColumn::make('description')
                    ->label(__('app.fields.description'))
                    ->wrap()
                    ->searchable(),
                TextColumn::make('lensConfig.patient.full_name')
                    ->label(__('app.fields.patient')),
                TextColumn::make('lensConfig.prescription_id')
                    ->label(__('app.fields.prescription'))
                    ->formatStateUsing(fn (): string => __('app.documents.print_formula'))
                    ->url(fn (SaleItem $record): ?string => $record->lensConfig?->prescription_id
                        ? route('documents.formula', $record->lensConfig->prescription_id)
                        : null)
                    ->openUrlInNewTab(),
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
                CreateAction::make()
                    ->hidden(fn (): bool => $this->isLocked())
                    ->mutateDataUsing(fn (array $data): array => [...$data, ...$this->taxSnapshotFor($data)]),
            ])
            ->recordActions([
                EditAction::make()
                    ->hidden(fn (): bool => $this->isLocked())
                    ->mutateDataUsing(fn (array $data, SaleItem $record): array => (int) ($data['product_id'] ?? 0) === (int) $record->product_id
                        ? $data
                        : [...$data, ...$this->taxSnapshotFor($data)]),
                DeleteAction::make()
                    ->hidden(fn (): bool => $this->isLocked()),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->hidden(fn (): bool => $this->isLocked()),
                ]),
            ]);
    }

    /** Editing lines after a return or a void could push the sale's value below what was already given back. */
    private function isLocked(): bool
    {
        $sale = $this->getOwnerRecord();

        return $sale->status === SaleStatus::Voided || $sale->returns()->exists();
    }

    /**
     * Tax snapshot for the line's product under the sale's company VAT regime.
     *
     * @param  array<string, mixed>  $data
     * @return array{tax_name: string|null, tax_rate: float|int, tax_treatment: string|null}
     */
    private function taxSnapshotFor(array $data): array
    {
        return SaleItem::taxSnapshotFor(Product::find($data['product_id'] ?? null), $this->getOwnerRecord()->company);
    }
}
