<?php

use App\Actions\RegisterSale;
use App\Enums\SaleStatus;
use App\Models\Customer;
use App\Models\PaymentMethod;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('creates a sale with items and sets totals', function () {
    $customer = Customer::factory()->create();
    $seller = User::factory()->seller()->create();

    $sale = app(RegisterSale::class)->handle([
        'customer_id' => $customer->id,
        'document_type' => 'order',
        'items' => [
            ['description' => 'Lente', 'quantity' => 2, 'unit_price' => 100_000],
            ['description' => 'Montura', 'quantity' => 1, 'unit_price' => 50_000],
        ],
    ], $seller);

    expect($sale->total)->toBe(250_000)
        ->and($sale->items)->toHaveCount(2)
        ->and($sale->seller_id)->toBe($seller->id)
        ->and($sale->created_by)->toBe($seller->id)
        ->and($sale->status)->toBe(SaleStatus::Draft);
});

it('records an optional initial payment and moves status to partial', function () {
    $customer = Customer::factory()->create();
    $seller = User::factory()->seller()->create();
    $method = PaymentMethod::factory()->create();

    $sale = app(RegisterSale::class)->handle([
        'customer_id' => $customer->id,
        'document_type' => 'layaway',
        'items' => [['description' => 'Promo', 'quantity' => 1, 'unit_price' => 375_000]],
        'payment' => ['payment_method_id' => $method->id, 'amount' => 50_000],
    ], $seller);

    expect($sale->total)->toBe(375_000)
        ->and($sale->balance)->toBe(325_000)
        ->and($sale->status)->toBe(SaleStatus::Partial)
        ->and($sale->payments)->toHaveCount(1);
});
