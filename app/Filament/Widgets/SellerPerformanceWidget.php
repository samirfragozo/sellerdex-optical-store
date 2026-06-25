<?php

namespace App\Filament\Widgets;

use App\Models\User;
use App\Support\ReportPeriod;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\TableWidget as BaseWidget;

class SellerPerformanceWidget extends BaseWidget
{
    use InteractsWithPageFilters;

    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        return auth()->user()?->isAdmin() === true;
    }

    public function table(Table $table): Table
    {
        [$start, $end] = ReportPeriod::fromFilters($this->pageFilters);

        return $table
            ->heading(__('app.reports.seller_performance'))
            ->query(
                User::query()
                    ->whereHas('sales')
                    ->withCount(['sales as sales_count' => fn ($q) => $q->whereBetween('sold_at', [$start, $end])])
                    ->withSum(['sales as sales_total' => fn ($q) => $q->whereBetween('sold_at', [$start, $end])], 'total')
            )
            ->columns([
                TextColumn::make('name')
                    ->label(__('app.fields.seller')),
                TextColumn::make('sales_count')
                    ->label(__('app.fields.number')),
                TextColumn::make('sales_total')
                    ->label(__('app.fields.total'))
                    ->money('COP'),
            ]);
    }
}
