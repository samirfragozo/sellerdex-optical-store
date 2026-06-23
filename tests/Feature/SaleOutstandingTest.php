<?php

use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\Sale;
use App\Models\SaleItem;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function saleWorth(int $total): Sale
{
    $sale = Sale::factory()->create();
    SaleItem::factory()->create(['sale_id' => $sale->id, 'quantity' => 1, 'unit_price' => $total]);

    return $sale->refresh();
}

it('returns only sales with an outstanding balance', function () {
    $method = PaymentMethod::factory()->create();

    $unpaid = saleWorth(300_000);
    $partial = saleWorth(300_000);
    Payment::factory()->create(['sale_id' => $partial->id, 'payment_method_id' => $method->id, 'amount' => 100_000]);
    $paid = saleWorth(300_000);
    Payment::factory()->create(['sale_id' => $paid->id, 'payment_method_id' => $method->id, 'amount' => 300_000]);

    $ids = Sale::query()->outstanding()->pluck('id');

    expect($ids)->toContain($unpaid->id)
        ->toContain($partial->id)
        ->not->toContain($paid->id);
});
