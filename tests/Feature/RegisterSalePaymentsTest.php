<?php

use App\Actions\RegisterSale;
use App\Models\Customer;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('creates one payment row per entry in the payments array', function () {
    $cash = PaymentMethod::factory()->create(['surcharge_percent' => 0]);
    $card = PaymentMethod::factory()->create(['surcharge_percent' => 5]);
    $product = Product::factory()->create(['price' => 100_000]);

    $sale = app(RegisterSale::class)->handle([
        'customer_id' => Customer::factory()->create()->id,
        'document_type' => 'order',
        'products' => [
            ['product_id' => $product->id, 'description' => $product->name, 'quantity' => 1, 'unit_price' => 100_000],
        ],
        'payments' => [
            ['payment_method_id' => $cash->id, 'amount' => 60_000],
            ['payment_method_id' => $card->id, 'amount' => 40_000],
        ],
    ], User::factory()->seller()->create());

    expect($sale->payments)->toHaveCount(2)
        ->and($sale->payments->sum('amount'))->toBe(100_000)
        ->and((float) $sale->surcharge_percent)->toBe(2.0); // (60000*0 + 40000*5) / 100000
});

it('skips zero-amount payment entries', function () {
    $product = Product::factory()->create(['price' => 50_000]);
    $method = PaymentMethod::factory()->create();

    $sale = app(RegisterSale::class)->handle([
        'customer_id' => Customer::factory()->create()->id,
        'document_type' => 'order',
        'products' => [
            ['product_id' => $product->id, 'description' => $product->name, 'quantity' => 1, 'unit_price' => 50_000],
        ],
        'payments' => [
            ['payment_method_id' => $method->id, 'amount' => 0],
        ],
    ], User::factory()->seller()->create());

    expect($sale->payments)->toHaveCount(0);
});

it('ignores a wrong client-supplied surcharge_percent when payments are present', function () {
    $cash = PaymentMethod::factory()->create(['surcharge_percent' => 0]);
    $card = PaymentMethod::factory()->create(['surcharge_percent' => 5]);
    $product = Product::factory()->create(['price' => 100_000]);

    $sale = app(RegisterSale::class)->handle([
        'customer_id' => Customer::factory()->create()->id,
        'document_type' => 'order',
        'surcharge_percent' => 0,
        'products' => [
            ['product_id' => $product->id, 'description' => $product->name, 'quantity' => 1, 'unit_price' => 100_000],
        ],
        'payments' => [
            ['payment_method_id' => $cash->id, 'amount' => 60_000],
            ['payment_method_id' => $card->id, 'amount' => 40_000],
        ],
    ], User::factory()->seller()->create());

    expect((float) $sale->surcharge_percent)->toBe(2.0); // (60000*0 + 40000*5) / 100000, not the client-sent 0
});

it('creates a sale with no payments when the array is empty', function () {
    $product = Product::factory()->create(['price' => 20_000]);

    $sale = app(RegisterSale::class)->handle([
        'customer_id' => Customer::factory()->create()->id,
        'document_type' => 'quote',
        'products' => [
            ['product_id' => $product->id, 'description' => $product->name, 'quantity' => 1, 'unit_price' => 20_000],
        ],
        'payments' => [],
    ], User::factory()->seller()->create());

    expect($sale->payments)->toHaveCount(0)
        ->and((float) $sale->surcharge_percent)->toBe(0.0);
});
