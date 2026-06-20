<?php

use App\Enums\SaleStatus;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\Sale;
use App\Models\SaleItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Activitylog\Models\Activity;

uses(RefreshDatabase::class);

function saleWithTotal(int $total): Sale
{
    $sale = Sale::factory()->create();
    SaleItem::factory()->create(['sale_id' => $sale->id, 'quantity' => 1, 'unit_price' => $total]);

    return $sale->refresh();
}

it('reduces the balance and moves the sale to partial then paid', function () {
    $method = PaymentMethod::factory()->create();
    $sale = saleWithTotal(300_000);

    expect($sale->balance)->toBe(300_000)
        ->and($sale->status)->toBe(SaleStatus::Draft);

    Payment::factory()->create(['sale_id' => $sale->id, 'payment_method_id' => $method->id, 'amount' => 50_000]);
    $sale->refresh();
    expect($sale->balance)->toBe(250_000)
        ->and($sale->status)->toBe(SaleStatus::Partial);

    Payment::factory()->create(['sale_id' => $sale->id, 'payment_method_id' => $method->id, 'amount' => 250_000]);
    $sale->refresh();
    expect($sale->balance)->toBe(0)
        ->and($sale->status)->toBe(SaleStatus::Paid);
});

it('returns to draft when the only payment is removed', function () {
    $method = PaymentMethod::factory()->create();
    $sale = saleWithTotal(300_000);
    $p1 = Payment::factory()->create(['sale_id' => $sale->id, 'payment_method_id' => $method->id, 'amount' => 300_000]);
    expect($sale->refresh()->status)->toBe(SaleStatus::Paid);

    $p1->delete();
    expect($sale->refresh()->status)->toBe(SaleStatus::Draft);
});

it('logs activity when a payment is created', function () {
    $payment = Payment::factory()->create();
    expect(Activity::where('subject_type', Payment::class)
        ->where('subject_id', $payment->id)->exists())->toBeTrue();
});
