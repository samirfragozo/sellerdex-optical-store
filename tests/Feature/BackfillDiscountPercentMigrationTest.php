<?php

use App\Models\Sale;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

it('backfills discount_percent from the legacy discount amount', function () {
    // Simulate a pre-existing "legacy" sale: subtotal 200_000, discount
    // 20_000 (a real peso amount), discount_percent left at its default 0
    // because the column didn't exist when this row was created.
    $sale = Sale::factory()->create(['subtotal' => 200_000, 'discount' => 20_000]);
    DB::table('sales')->whereKey($sale->id)->update(['discount_percent' => 0]);

    $migration = require database_path('migrations/2026_09_13_100002_backfill_discount_percent_on_sales.php');
    $migration->up();

    expect((float) $sale->refresh()->discount_percent)->toBe(10.0);
});

it('leaves rows with no discount untouched', function () {
    $sale = Sale::factory()->create(['subtotal' => 200_000, 'discount' => 0]);

    $migration = require database_path('migrations/2026_09_13_100002_backfill_discount_percent_on_sales.php');
    $migration->up();

    expect((float) $sale->refresh()->discount_percent)->toBe(0.0);
});
