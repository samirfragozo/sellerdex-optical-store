<?php

namespace App\Support;

use Carbon\CarbonImmutable;

class ReportPeriod
{
    /**
     * Resolve a [start, end] date range from the Reports page filters,
     * defaulting to the current month when no range is set.
     *
     * @param  array<string, mixed>|null  $filters
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    public static function fromFilters(?array $filters): array
    {
        $from = $filters['from'] ?? null;
        $to = $filters['to'] ?? null;

        $start = $from ? CarbonImmutable::parse($from)->startOfDay() : CarbonImmutable::now()->startOfMonth();
        $end = $to ? CarbonImmutable::parse($to)->endOfDay() : CarbonImmutable::now()->endOfMonth();

        return [$start, $end];
    }
}
