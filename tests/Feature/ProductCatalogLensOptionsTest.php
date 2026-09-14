<?php

use App\Models\OptionGroup;
use App\Models\Product;
use Database\Seeders\ProductCatalogSeeder;
use Database\Seeders\ProductCategorySeeder;

it('seeds one option-driven base product per lens design', function () {
    $this->seed(ProductCategorySeeder::class);
    $this->seed(ProductCatalogSeeder::class);

    foreach (['ML-MONOFOCAL', 'ML-BIFOCAL', 'ML-PROGRESIVO'] as $sku) {
        $product = Product::where('sku', $sku)->first();
        expect($product)->not->toBeNull()
            ->and($product->optionGroups)->toHaveCount(3)
            ->and($product->optionGroups->pluck('name'))
            ->each(fn ($name) => $name->toContain($product->specs['design']));
    }

    expect(OptionGroup::where('name', 'Filtro Monofocal')->exists())->toBeTrue();
});

it('does not seed the old flat per-combination lens SKUs', function () {
    $this->seed(ProductCategorySeeder::class);
    $this->seed(ProductCatalogSeeder::class);

    expect(Product::where('sku', 'like', 'ML-0%')->count())->toBe(0);
});
