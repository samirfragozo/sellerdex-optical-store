<?php

use App\Models\OptionGroup;
use App\Models\Product;
use Database\Seeders\ProductCatalogSeeder;
use Database\Seeders\ProductCategorySeeder;

it('seeds a demo option-driven lens alongside the flat lens catalog', function () {
    $this->seed(ProductCategorySeeder::class);
    $this->seed(ProductCatalogSeeder::class);

    $product = Product::where('sku', 'LOPT-MONOFOCAL')->first();
    expect($product)->not->toBeNull()
        ->and($product->optionGroups)->toHaveCount(2)
        ->and(OptionGroup::where('name', 'Material')->exists())->toBeTrue();

    // The existing flat catalog is untouched.
    expect(Product::where('sku', 'ML-001')->exists())->toBeTrue();
});
