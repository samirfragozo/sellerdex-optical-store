<?php

use App\Enums\CashCloseType;
use App\Models\Expense;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Services\CashCloseService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

uses(RefreshDatabase::class);

function makeSale(string $date, int $total): Sale
{
    $sale = Sale::factory()->create(['sold_at' => $date]);
    SaleItem::factory()->create(['sale_id' => $sale->id, 'quantity' => 1, 'unit_price' => $total]);

    return $sale->refresh();
}

it('computes a daily close from sales, payments and expenses', function () {
    $cash = PaymentMethod::factory()->create(['is_default' => true, 'name' => 'Efectivo']);
    $transfer = PaymentMethod::factory()->create(['is_default' => false, 'name' => 'Transferencia']);
    $today = '2026-06-10';

    $sale = makeSale($today, 300_000);
    Payment::factory()->create(['sale_id' => $sale->id, 'payment_method_id' => $cash->id, 'amount' => 100_000, 'paid_at' => $today]);
    Payment::factory()->create(['sale_id' => $sale->id, 'payment_method_id' => $transfer->id, 'amount' => 50_000, 'paid_at' => $today]);
    Expense::factory()->create(['amount' => 30_000, 'payment_method_id' => $cash->id, 'spent_at' => $today]);

    $snap = app(CashCloseService::class)->compute(CashCloseType::Daily, Carbon::parse($today), openingCash: 20_000);

    expect($snap['total_sales'])->toBe(300_000)
        ->and($snap['total_collected'])->toBe(150_000)
        ->and($snap['total_expenses'])->toBe(30_000)
        ->and($snap['expected_cash'])->toBe(90_000) // 20k opening + 100k cash - 30k cash expense
        ->and($snap['total_receivable'])->toBe(150_000) // 300k - 150k paid
        ->and($snap['collected_by_method'][$cash->id])->toBe(100_000)
        ->and($snap['collected_by_method'][$transfer->id])->toBe(50_000);
});

it('ignores activity outside the period', function () {
    $cash = PaymentMethod::factory()->create(['is_default' => true]);
    $sale = makeSale('2026-06-10', 100_000);
    Payment::factory()->create(['sale_id' => $sale->id, 'payment_method_id' => $cash->id, 'amount' => 100_000, 'paid_at' => '2026-06-11']);

    $snap = app(CashCloseService::class)->compute(CashCloseType::Daily, Carbon::parse('2026-06-10'));

    expect($snap['total_collected'])->toBe(0)
        ->and($snap['total_sales'])->toBe(100_000);
});

it('aggregates a monthly period', function () {
    $cash = PaymentMethod::factory()->create(['is_default' => true]);
    $s1 = makeSale('2026-06-03', 100_000);
    $s2 = makeSale('2026-06-20', 200_000);
    Payment::factory()->create(['sale_id' => $s1->id, 'payment_method_id' => $cash->id, 'amount' => 100_000, 'paid_at' => '2026-06-05']);

    $snap = app(CashCloseService::class)->compute(CashCloseType::Monthly, Carbon::parse('2026-06-15'));

    expect($snap['total_sales'])->toBe(300_000)
        ->and($snap['total_collected'])->toBe(100_000)
        ->and($snap['period_start'])->toBe('2026-06-01')
        ->and($snap['period_end'])->toBe('2026-06-30');
});
