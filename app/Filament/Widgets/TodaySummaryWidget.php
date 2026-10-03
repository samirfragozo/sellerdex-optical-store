<?php

namespace App\Filament\Widgets;

use App\Enums\SaleDocumentType;
use App\Enums\SaleStatus;
use App\Models\Payment;
use App\Models\Sale;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\DB;

class TodaySummaryWidget extends BaseWidget
{
    public static function canView(): bool
    {
        return auth()->user()?->isAdmin() === true;
    }

    protected function getStats(): array
    {
        $today = now()->toDateString();

        $countsForSale = fn ($query) => $query
            ->where('document_type', '!=', SaleDocumentType::Quote->value)
            ->where('status', '!=', SaleStatus::Voided->value);

        $salesToday = (int) Sale::query()
            ->tap($countsForSale)
            ->whereDate('sold_at', $today)
            ->sum(DB::raw(Sale::NET_VALUE_SQL));

        $collectedToday = Payment::query()->realMoney()->whereDate('paid_at', $today)->sum('amount');

        // ponytail: loads every open sale to sum balances, SQL aggregate if this ever gets slow
        $receivable = (int) Sale::query()->tap($countsForSale)->get()->sum('balance');

        $pendingDeliveries = Sale::query()
            ->tap($countsForSale)
            ->where('is_delivered', false)
            ->count();

        return [
            Stat::make(__('app.reports.sales_today'), '$'.number_format($salesToday)),
            Stat::make(__('app.reports.collected_today'), '$'.number_format((int) $collectedToday)),
            Stat::make(__('app.reports.receivable_total'), '$'.number_format($receivable)),
            Stat::make(__('app.reports.pending_deliveries'), (string) $pendingDeliveries),
        ];
    }
}
