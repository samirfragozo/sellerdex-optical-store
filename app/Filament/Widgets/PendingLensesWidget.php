<?php

namespace App\Filament\Widgets;

use App\Models\Supplier;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Database\Eloquent\Builder;

class PendingLensesWidget extends BaseWidget
{
    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        return auth()->check();
    }

    public function table(Table $table): Table
    {
        return $table
            ->heading(__('app.reports.pending_lenses'))
            ->query(
                Supplier::query()
                    ->laboratories()
                    ->whereHas('lensOrders', fn (Builder $query) => $query->pending())
                    ->withCount(['lensOrders as pending_count' => fn (Builder $query) => $query->pending()])
            )
            ->columns([
                TextColumn::make('name')->label(__('app.fields.laboratory')),
                TextColumn::make('pending_count')->label(__('app.reports.pending_count')),
            ]);
    }
}
