<?php

use App\Enums\SaleStatus;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('auto-generates a unique sequential number', function () {
    $a = Sale::factory()->create();
    $b = Sale::factory()->create();

    expect($a->number)->not->toBeEmpty()
        ->and($b->number)->not->toBe($a->number);
});

it('computes line_total from quantity and unit_price on save', function () {
    $item = SaleItem::factory()->create(['quantity' => 3, 'unit_price' => 50_000]);
    expect($item->line_total)->toBe(150_000);
});

it('recalculates sale totals from its items', function () {
    $sale = Sale::factory()->create(['discount' => 0]);
    SaleItem::factory()->create(['sale_id' => $sale->id, 'quantity' => 2, 'unit_price' => 100_000]);
    SaleItem::factory()->create(['sale_id' => $sale->id, 'quantity' => 1, 'unit_price' => 50_000]);

    expect($sale->refresh()->subtotal)->toBe(250_000)
        ->and($sale->total)->toBe(250_000);
});

it('computes the discount and the included VAT via recalculateTotals', function () {
    $sale = Sale::factory()->create([
        'discount_percent' => 10,
        'surcharge_percent' => 0,
    ]);
    $product = Product::factory()->create();
    $sale->items()->create([
        'product_id' => $product->id,
        'description' => 'Montura',
        'quantity' => 1,
        'unit_price' => 100_000,
        'unit_cost' => 0,
        'tax_name' => 'IVA 19%',
        'tax_rate' => 19,
    ]);

    $sale->recalculateTotals();
    $sale->refresh();

    expect($sale->subtotal)->toBe(100_000)
        ->and($sale->discount)->toBe(10_000)   // 10% of 100_000
        ->and($sale->tax_amount)->toBe(14_369) // inclusive 15_966 prorated by base/subtotal = 90_000/100_000
        ->and($sale->total)->toBe(90_000);     // tax is already inside the price
});

it('defaults to draft status', function () {
    expect(Sale::factory()->create()->status)->toBe(SaleStatus::Draft);
});
