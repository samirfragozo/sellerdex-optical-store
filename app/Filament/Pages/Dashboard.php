<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\AccountsReceivableWidget;
use App\Filament\Widgets\LowStockWidget;
use App\Filament\Widgets\PendingLensesWidget;
use App\Filament\Widgets\TodaySummaryWidget;
use Filament\Pages\Dashboard as BaseDashboard;

class Dashboard extends BaseDashboard
{
    /**
     * Operational, point-in-time widgets. Period analysis lives in Reports.
     *
     * @return array<class-string>
     */
    public function getWidgets(): array
    {
        return [
            TodaySummaryWidget::class,
            AccountsReceivableWidget::class,
            LowStockWidget::class,
            PendingLensesWidget::class,
        ];
    }

    public function getColumns(): int|array
    {
        return 2;
    }
}
