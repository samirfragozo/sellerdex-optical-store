<?php

use App\Models\Option;
use App\Models\OptionGroup;
use App\Models\Product;

it('links a variant product to its base and its defining options', function () {
    $base = Product::factory()->create();
    $group = OptionGroup::factory()->create(['name' => 'Color']);
    $option = Option::factory()->for($group, 'group')->create(['name' => 'Negro']);

    $variant = Product::factory()->create(['base_product_id' => $base->id]);
    $variant->variantOptions()->attach($option->id);

    expect($variant->baseProduct->is($base))->toBeTrue()
        ->and($base->variants->pluck('id')->all())->toBe([$variant->id])
        ->and($variant->variantOptions->pluck('id')->all())->toBe([$option->id]);
});

it('deleting a base product does not delete its variants', function () {
    $base = Product::factory()->create();
    $variant = Product::factory()->create(['base_product_id' => $base->id]);

    $base->forceDelete();

    expect(Product::find($variant->id))->not->toBeNull()
        ->and($variant->fresh()->base_product_id)->toBeNull();
});
