<?php

use App\Actions\RegisterSale;
use App\Models\Customer;
use App\Models\Product;
use App\Models\User;

it('adds a bundled addition line when its host product is sold', function () {
    $host = Product::factory()->create(['price' => 100000, 'cost' => 40000]);
    $addition = Product::factory()->create(['price' => 25000, 'cost' => 12000, 'name' => 'Solución LC']);
    $host->additions()->attach($addition->id, ['price' => -25000, 'quantity' => 1, 'is_active' => true]);

    $customer = Customer::factory()->create();
    $seller = User::factory()->seller()->create();

    $sale = app(RegisterSale::class)->handle([
        'customer_id' => $customer->id,
        'document_type' => 'order',
        'items' => [
            ['product_id' => $host->id, 'description' => $host->name, 'quantity' => 1, 'unit_price' => $host->price],
        ],
    ], $seller);

    $additionItem = $sale->items->firstWhere('product_id', $addition->id);

    expect($sale->items)->toHaveCount(2)
        ->and($additionItem)->not->toBeNull()
        ->and($additionItem->unit_price)->toBe(0)
        ->and($additionItem->unit_cost)->toBe(12000)
        ->and($additionItem->quantity)->toBe(1);
});

it('adds an addition with a non-zero resolved price and a quantity greater than one', function () {
    $host = Product::factory()->create(['price' => 100000, 'cost' => 40000]);
    $addition = Product::factory()->create(['price' => 5000, 'cost' => 2000]);
    $host->additions()->attach($addition->id, ['price' => 1000, 'quantity' => 3, 'is_active' => true]);

    $customer = Customer::factory()->create();
    $seller = User::factory()->seller()->create();

    $sale = app(RegisterSale::class)->handle([
        'customer_id' => $customer->id,
        'document_type' => 'order',
        'items' => [
            ['product_id' => $host->id, 'description' => $host->name, 'quantity' => 1, 'unit_price' => $host->price],
        ],
    ], $seller);

    $additionItem = $sale->items->firstWhere('product_id', $addition->id);

    expect($additionItem->unit_price)->toBe(6000)
        ->and($additionItem->unit_cost)->toBe(2000)
        ->and($additionItem->quantity)->toBe(3);
});

it('does not add an inactive addition', function () {
    $host = Product::factory()->create();
    $addition = Product::factory()->create();
    $host->additions()->attach($addition->id, ['price' => 0, 'quantity' => 1, 'is_active' => false]);

    $customer = Customer::factory()->create();
    $seller = User::factory()->seller()->create();

    $sale = app(RegisterSale::class)->handle([
        'customer_id' => $customer->id,
        'document_type' => 'order',
        'items' => [
            ['product_id' => $host->id, 'description' => $host->name, 'quantity' => 1, 'unit_price' => $host->price],
        ],
    ], $seller);

    expect($sale->items)->toHaveCount(1);
});

it('does not add anything for a product with no additions', function () {
    $host = Product::factory()->create();

    $customer = Customer::factory()->create();
    $seller = User::factory()->seller()->create();

    $sale = app(RegisterSale::class)->handle([
        'customer_id' => $customer->id,
        'document_type' => 'order',
        'items' => [
            ['product_id' => $host->id, 'description' => $host->name, 'quantity' => 1, 'unit_price' => $host->price],
        ],
    ], $seller);

    expect($sale->items)->toHaveCount(1);
});
