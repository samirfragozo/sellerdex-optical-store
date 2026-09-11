<?php

use App\Models\Product;

it('links an addition product to its host with price, quantity, and active pivot fields', function () {
    $host = Product::factory()->create();
    $addition = Product::factory()->create(['price' => 25000, 'cost' => 12000]);

    $host->additions()->attach($addition->id, ['price' => -25000, 'quantity' => 2, 'is_active' => true]);

    $attached = $host->additions()->first();

    expect($attached->id)->toBe($addition->id)
        ->and($attached->pivot->price)->toBe(-25000)
        ->and($attached->pivot->quantity)->toBe(2)
        ->and($attached->pivot->is_active)->toBeTrue();
});

it('excludes inactive additions when filtered by the pivot', function () {
    $host = Product::factory()->create();
    $active = Product::factory()->create();
    $inactive = Product::factory()->create();

    $host->additions()->attach($active->id, ['price' => 0, 'quantity' => 1, 'is_active' => true]);
    $host->additions()->attach($inactive->id, ['price' => 0, 'quantity' => 1, 'is_active' => false]);

    $activeOnly = $host->additions()->wherePivot('is_active', true)->get();

    expect($activeOnly)->toHaveCount(1)
        ->and($activeOnly->first()->id)->toBe($active->id);
});
