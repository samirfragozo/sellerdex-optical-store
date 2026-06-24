<?php

use App\Actions\RegisterSale;
use App\Models\Customer;
use App\Models\PaymentMethod;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('applies the surcharge percent to the total', function () {
    $sale = Sale::factory()->create(['discount' => 0, 'surcharge_percent' => 7]);
    SaleItem::factory()->create(['sale_id' => $sale->id, 'quantity' => 1, 'unit_price' => 200000]);

    expect($sale->refresh()->total)->toBe(214000); // 200000 * 1.07
});

it('derives the sale surcharge from the payment method', function () {
    $addi = PaymentMethod::factory()->create(['surcharge_percent' => 7]);

    $sale = app(RegisterSale::class)->handle([
        'customer_id' => Customer::factory()->create()->id,
        'document_type' => 'order',
        'items' => [['description' => 'Lente', 'quantity' => 1, 'unit_price' => 100000]],
        'payment' => ['payment_method_id' => $addi->id, 'amount' => 50000],
    ], User::factory()->seller()->create());

    expect((float) $sale->surcharge_percent)->toBe(7.0)
        ->and($sale->total)->toBe(107000); // 100000 * 1.07
});
