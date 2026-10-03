<?php

namespace App\Filament\Widgets;

use App\Models\Expense;
use App\Models\Payment;
use App\Models\Sale;
use App\Support\ReportPeriod;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\DB;

class FinancialSummaryWidget extends BaseWidget
{
    use InteractsWithPageFilters;

    public static function canView(): bool
    {
        return auth()->user()?->isAdmin() === true;
    }

    protected function getStats(): array
    {
        [$start, $end] = ReportPeriod::fromFilters($this->pageFilters);

        $sales = Sale::notVoided()->whereBetween('sold_at', [$start, $end])->sum(DB::raw(Sale::NET_VALUE_SQL));
        $collected = Payment::realMoney()->whereBetween('paid_at', [$start, $end])->sum('amount');
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
