<?php

namespace App\Services;

use App\Enums\CashCloseType;
use App\Models\CashRegisterSession;
use App\Models\Expense;
use App\Models\Payment;
use App\Models\Sale;
use Carbon\CarbonInterface;

class CashCloseService
{
    /**
     * Build the reconciliation snapshot for a period.
     *
     * @return array<string,mixed>
     */
    public function compute(CashCloseType $type, CarbonInterface $date): array
    {
        [$start, $end] = $this->period($type, $date);

        $totalSales = (int) Sale::whereBetween('sold_at', [$start, $end])->sum('total');

        $payments = Payment::whereBetween('paid_at', [$start, $end])->get();
        $totalCollected = (int) $payments->sum('amount');
        $collectedByMethod = $payments->groupBy('payment_method_id')
            ->map(fn ($group) => (int) $group->sum('amount'))->all();

        $totalExpenses = (int) Expense::whereBetween('spent_at', [$start, $end])->sum('amount');

        // Drawer figures come from the cash sessions, not from dates: a session counts where it opened/closed.
        $openingCash = (int) CashRegisterSession::whereBetween('opened_at', [$start, $end])->sum('opening_cash');
        $closedSessions = CashRegisterSession::whereBetween('closed_at', [$start, $end]);
        $expectedCash = (int) (clone $closedSessions)->sum('expected_cash');
        $countedCash = (int) (clone $closedSessions)->sum('closed_cash');

        // Outstanding receivable across all non-voided sales (snapshot, not period-bound).
        $totalReceivable = Sale::query()->get()
            ->reduce(fn (int $carry, Sale $sale) => $carry + $sale->balance, 0);

        return [
            'type' => $type->value,
            'period_start' => $start->toDateString(),
            'period_end' => $end->toDateString(),
            'opening_cash' => $openingCash,
            'total_sales' => $totalSales,
            'total_collected' => $totalCollected,
            'collected_by_method' => $collectedByMethod,
            'total_expenses' => $totalExpenses,
            'total_receivable' => $totalReceivable,
            'expected_cash' => $expectedCash,
            'counted_cash' => $countedCash,
        ];
    }

    /**
     * Returns [start, end] Carbon instances for the period.
     * Using startOfDay/endOfDay (or startOfMonth/endOfMonth) produces full datetime
     * bounds that SQLite can compare against its stored datetime strings.
     *
     * @return array{0: CarbonInterface, 1: CarbonInterface}
     */
    private function period(CashCloseType $type, CarbonInterface $date): array
    {
        return $type === CashCloseType::Monthly
            ? [$date->copy()->startOfMonth(), $date->copy()->endOfMonth()]
            : [$date->copy()->startOfDay(), $date->copy()->endOfDay()];
    }
}
