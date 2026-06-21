<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\AccountsReceivableWidget;
use App\Filament\Widgets\FinancialSummaryWidget;
use App\Filament\Widgets\LowStockWidget;
use App\Filament\Widgets\SellerPerformanceWidget;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;

class Reports extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChartBar;

    protected string $view = 'filament.pages.reports';

    public static function getNavigationLabel(): string
    {
        return __('app.reports.title');
    }

    public function getTitle(): string
    {
        return __('app.reports.title');
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->isAdmin() === true;
    }

    public static function shouldRegisterNavigation(): bool
    {
        return auth()->user()?->isAdmin() === true;
    }

    protected function getHeaderWidgets(): array
    {
        return [
            FinancialSummaryWidget::class,
            AccountsReceivableWidget::class,
            SellerPerformanceWidget::class,
            LowStockWidget::class,
        ];
    }

    public function getHeaderWidgetsColumns(): int|array
    {
        return 2;
    }
}
