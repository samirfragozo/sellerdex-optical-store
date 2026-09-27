<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\AccountsReceivableWidget;
use App\Filament\Widgets\LowStockWidget;
use App\Filament\Widgets\PendingLensesWidget;
use App\Filament\Widgets\TodaySummaryWidget;
use App\Models\Company;
use Filament\Pages\Dashboard as BaseDashboard;
use Illuminate\Support\Facades\Auth;

class Dashboard extends BaseDashboard
{
    public function mount(): void
    {
        $user = Auth::user();

        if ($user?->company_id !== null && $user->isAdmin() && Company::current()->needsOnboarding()) {
            $this->redirect(LensOnboarding::getUrl());
        }
    }

    /**
     * Operational, point-in-time widgets. Period analysis lives in Reports.
     *
     * @return array<class-string>
     */
    public function getWidgets(): array
    {
        return [
            TodaySummaryWidget::class,
            PendingLensesWidget::class,
            AccountsReceivableWidget::class,
            LowStockWidget::class,
        ];
    }

    public function getColumns(): int|array
    {
        return 2;
    }
}
