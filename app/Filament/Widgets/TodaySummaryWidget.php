<?php

namespace App\Filament\Widgets;

use App\Enums\SaleDocumentType;
use App\Enums\SaleStatus;
use App\Models\Payment;
use App\Models\Sale;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

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

        $salesToday = Sale::query()
            ->tap($countsForSale)
            ->whereDate('sold_at', $today)
            ->sum('total');

        $collectedToday = Payment::query()->whereDate('paid_at', $today)->sum('amount');

        $salesTotal = Sale::query()->tap($countsForSale)->sum('total');
        $paidTotal = Payment::query()
            ->whereHas('sale', fn ($q) => $countsForSale($q))
            ->sum('amount');
        $receivable = max(0, (int) $salesTotal - (int) $paidTotal);

        $pendingDeliveries = Sale::query()
            ->tap($countsForSale)
            ->where('is_delivered', false)
            ->count();

        return [
            Stat::make(__('app.reports.sales_today'), '$'.number_format((int) $salesToday)),
            Stat::make(__('app.reports.collected_today'), '$'.number_format((int) $collectedToday)),
            Stat::make(__('app.reports.receivable_total'), '$'.number_format($receivable)),
            Stat::make(__('app.reports.pending_deliveries'), (string) $pendingDeliveries),
        ];
    }
}
