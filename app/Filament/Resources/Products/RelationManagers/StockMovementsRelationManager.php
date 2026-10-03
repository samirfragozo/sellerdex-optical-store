<?php

namespace App\Filament\Resources\Products\RelationManagers;

use App\Models\Company;
use App\Models\PurchaseOrder;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\StockMovement;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/** Read-only kardex: every stock change of the product with the balance it left. */
class StockMovementsRelationManager extends RelationManager
{
    protected static string $relationship = 'stockMovements';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('app.inventory.kardex');
    }

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        // Admin only (like the low-stock widget): there is no StockMovement policy, so the parent check lets any product viewer in.
        return auth()->user()?->isAdmin() === true
            && $ownerRecord->is_stockable
            && Company::current()->tracksInventory()
            && parent::canViewForRecord($ownerRecord, $pageClass);
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with([
                'user:id,name',
                'source' => fn (MorphTo $source) => $source->morphWith([SaleItem::class => ['sale']]),
            ]))
            ->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('created_at')
                    ->label(__('app.inventory.date'))
                    ->dateTime(),
                TextColumn::make('type')
                    ->label(__('app.inventory.type'))
                    ->badge(),
                TextColumn::make('quantity')
                    ->label(__('app.inventory.quantity'))
                    ->formatStateUsing(fn (int $state): string => $state > 0 ? '+'.$state : (string) $state),
                TextColumn::make('balance_after')
                    ->label(__('app.inventory.balance')),
                TextColumn::make('source')
                    ->label(__('app.inventory.source'))
                    ->state(fn (StockMovement $record): string => self::sourceLabel($record)),
                TextColumn::make('reason')
                    ->label(__('app.inventory.reason')),
                TextColumn::make('user.name')
                    ->label(__('app.inventory.user')),
            ]);
    }

    /** Blank when the movement is manual or its source was deleted. */
    private static function sourceLabel(StockMovement $movement): string
    {
        $source = $movement->source;

        return match (true) {
            $source instanceof SaleItem => self::saleLabel($source->sale),
            $source instanceof Sale => self::saleLabel($source),
            $source instanceof PurchaseOrder => __('app.inventory.purchase_source', ['number' => $source->number]),
            default => '',
        };
    }

    private static function saleLabel(?Sale $sale): string
    {
        return $sale === null ? '' : __('app.inventory.sale_source', ['number' => $sale->number]);
    }
}
