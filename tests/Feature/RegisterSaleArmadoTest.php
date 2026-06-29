<?php

use App\Models\Sale;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

it('has a nullable group_key column on sale_items', function () {
    expect(Schema::hasColumn('sale_items', 'group_key'))->toBeTrue();
});

it('persists group_key on a sale item', function () {
    $sale = Sale::factory()->create();
    $item = $sale->items()->create([
        'group_key' => 'g1',
        'description' => 'Línea de prueba',
        'quantity' => 1,
        'unit_price' => 0,
    ]);

    expect($item->fresh()->group_key)->toBe('g1');
});
