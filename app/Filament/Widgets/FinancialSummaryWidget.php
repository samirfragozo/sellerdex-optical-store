<?php

namespace App\Filament\Widgets;

use App\Models\Expense;
use App\Models\Payment;
use App\Models\Sale;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class FinancialSummaryWidget extends BaseWidget
{
    protected function getStats(): array
    {
        $start = now()->startOfMonth();
        $end = now()->endOfMonth();

        $sales = Sale::whereBetween('sold_at', [$start, $end])->sum('total');
        $collected = Payment::whereBetween('paid_at', [$start, $end])->sum('amount');
        $expenses = Expense::whereBetween('spent_at', [$start, $end])->sum('amount');
        $profit = $collected - $expenses;

        return [
            Stat::make(__('app.reports.sales'), '$'.number_format((int) $sales)),
            Stat::make(__('app.reports.collected'), '$'.number_format((int) $collected)),
            Stat::make(__('app.reports.expenses'), '$'.number_format((int) $expenses)),
            Stat::make(__('app.reports.profit'), '$'.number_format((int) $profit)),
        ];
    }
}
