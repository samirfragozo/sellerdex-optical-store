<?php

namespace App\Filament\Widgets;

use App\Enums\SaleStatus;
use App\Models\ProductCategory;
use App\Support\ReportPeriod;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Database\Eloquent\Builder;

class SalesByCategoryWidget extends BaseWidget
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

        $inPeriod = fn (Builder $query) => $query->whereHas('sale', fn (Builder $sale) => $sale
            ->whereBetween('sold_at', [$start, $end])
            ->where('status', '!=', SaleStatus::Voided->value));

        return $table
            ->heading(__('app.reports.sales_by_category'))
            ->query(
                ProductCategory::query()
                    ->withSum(['saleItems as units' => $inPeriod], 'quantity')
                    ->withSum(['saleItems as amount' => $inPeriod], 'line_total')
            )
            ->columns([
                TextColumn::make('name')->label(__('app.fields.category')),
                TextColumn::make('units')->label(__('app.reports.units'))->default(0),
                TextColumn::make('amount')->label(__('app.fields.total'))->default(0)->money('COP'),
            ]);
    }
}
