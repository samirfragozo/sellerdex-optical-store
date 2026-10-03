<?php

namespace App\Filament\Widgets;

use App\Models\Company;
use App\Models\Product;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class LowStockWidget extends BaseWidget
{
    public static function canView(): bool
    {
        return auth()->user()?->isAdmin() === true && Company::current()->tracksInventory();
    }

    public function table(Table $table): Table
    {
        return $table
            ->heading(__('app.reports.low_stock'))
            ->query(
                Product::query()
                    ->where('is_stockable', true)
                    ->where('stock', '<=', 5)
            )
            ->columns([
                TextColumn::make('name')
                    ->label(__('app.fields.name')),
                TextColumn::make('category.name')
                    ->label(__('app.fields.category')),
                TextColumn::make('stock')
                    ->label(__('app.fields.stock')),
            ]);
    }
}
