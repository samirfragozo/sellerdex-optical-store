<?php

use App\Models\OptionGroup;
use App\Models\Product;
use Database\Seeders\ProductCatalogSeeder;
use Database\Seeders\ProductCategorySeeder;

it('seeds a frame base with structured options and materializes its 18 known variants', function () {
    $this->seed(ProductCategorySeeder::class);
    $this->seed(ProductCatalogSeeder::class);

    $base = Product::where('sku', 'MNT-BASE')->first();
    expect($base)->not->toBeNull()
        ->and($base->optionGroups)->toHaveCount(2)
        ->and(OptionGroup::where('name', 'Estructura')->exists())->toBeTrue()
        ->and($base->variants()->count())->toBe(18);

    $variant = Product::where('sku', 'MNT-COMPLETAS-ACETATO')->first();
    expect($variant)->not->toBeNull()
        ->and($variant->base_product_id)->toBe($base->id)
        ->and($variant->variantOptions)->toHaveCount(2)
        ->and($variant->is_pos_selectable)->toBeFalse();

    // Guards against the frame's material group colliding with a lens design's
    // own "Material {design}" group (they must remain separate OptionGroup rows).
    expect(OptionGroup::where('name', 'Material Monofocal')->first()->options)->toHaveCount(6)
        ->and(OptionGroup::where('name', 'Material de Montura')->first()->options)->toHaveCount(6);
});

it('re-running the seeder does not duplicate frame variants', function () {
    $this->seed(ProductCategorySeeder::class);
    $this->seed(ProductCatalogSeeder::class);
    $this->seed(ProductCatalogSeeder::class);

    $base = Product::where('sku', 'MNT-BASE')->first();
    expect($base->variants()->count())->toBe(18);
});
