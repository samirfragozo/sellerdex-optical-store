<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\FinancialSummaryWidget;
use App\Filament\Widgets\SellerPerformanceWidget;
use BackedEnum;
use Carbon\CarbonImmutable;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Pages\Dashboard;
use Filament\Pages\Dashboard\Concerns\HasFiltersForm;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class Reports extends Dashboard
{
    use HasFiltersForm;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChartBar;

    protected static string $routePath = '/reports';

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

    /**
     * @return array<class-string>
     */
    public function getWidgets(): array
    {
        return [
            FinancialSummaryWidget::class,
            SellerPerformanceWidget::class,
        ];
    }

    public function getColumns(): int|array
    {
        return 2;
    }

    public function filtersForm(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('period')
                ->label(__('app.reports.period'))
                ->options([
                    'week' => __('app.reports.this_week'),
                    'month' => __('app.reports.this_month'),
                    'year' => __('app.reports.this_year'),
                    'custom' => __('app.reports.custom'),
                ])
                ->default('month')
                ->selectablePlaceholder(false)
                ->live()
                ->afterStateUpdated(function (Set $set, ?string $state): void {
                    [$from, $to] = self::rangeForPeriod($state ?? 'month');

                    if ($from !== null) {
                        $set('from', $from);
                        $set('to', $to);
                    }
                }),
            DatePicker::make('from')
                ->label(__('app.reports.from'))
                ->default(now()->startOfMonth()),
            DatePicker::make('to')
                ->label(__('app.reports.to'))
                ->default(now()->endOfMonth()),
        ]);
    }

    /**
     * Resolve [from, to] dates for a named period. Returns [null, null] for "custom".
     *
     * @return array{0: ?string, 1: ?string}
     */
    public static function rangeForPeriod(string $period): array
    {
        $now = CarbonImmutable::now();

        return match ($period) {
            'week' => [$now->startOfWeek()->toDateString(), $now->endOfWeek()->toDateString()],
            'month' => [$now->startOfMonth()->toDateString(), $now->endOfMonth()->toDateString()],
            'year' => [$now->startOfYear()->toDateString(), $now->endOfYear()->toDateString()],
            default => [null, null],
        };
    }
}
