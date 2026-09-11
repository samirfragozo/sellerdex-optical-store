<?php

use App\Models\Product;
use Database\Seeders\ProductCatalogSeeder;
use Database\Seeders\ProductCategorySeeder;

it('bundles the LC solution as a product addition on every formulated contact lens SKU', function () {
    $this->seed(ProductCategorySeeder::class);
    $this->seed(ProductCatalogSeeder::class);

    $solution = Product::where('sku', 'ACC-SOLUCION-LC')->first();
    expect($solution)->not->toBeNull();

    $formulatedSkus = [
        'ACC-LC-FORM-X1',
        'ACC-LC-CONFORTVUE-X3',
        'ACC-LC-JJ-X3',
        'ACC-LC-AIROPTIX-X3',
        'ACC-LC-AIROPTIX-CYL-X3',
    ];

    foreach ($formulatedSkus as $sku) {
        $product = Product::where('sku', $sku)->first();
        $addition = $product->additions()->first();

        expect($addition)->not->toBeNull()
            ->and($addition->id)->toBe($solution->id)
            ->and($addition->price + $addition->pivot->price)->toBe(0)
            ->and($addition->pivot->quantity)->toBe(1)
            ->and($addition->pivot->is_active)->toBeTrue()
            ->and($product->specs['includes'] ?? null)->toBeNull();
    }
});

it('does not bundle an addition on the non-formulated cosmetic contact lens SKU', function () {
    $this->seed(ProductCategorySeeder::class);
    $this->seed(ProductCatalogSeeder::class);

    $cosmetic = Product::where('sku', 'ACC-LC-COSMETICOS')->first();

    expect($cosmetic->additions)->toHaveCount(0);
});

it('re-running the seeder does not duplicate the addition attachment', function () {
    $this->seed(ProductCategorySeeder::class);
    $this->seed(ProductCatalogSeeder::class);
    $this->seed(ProductCatalogSeeder::class);

    $product = Product::where('sku', 'ACC-LC-FORM-X1')->first();

    expect($product->additions()->count())->toBe(1);
});
