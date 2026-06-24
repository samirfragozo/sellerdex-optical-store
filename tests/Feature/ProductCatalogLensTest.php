<?php

use App\Models\Product;
use Database\Seeders\ProductCatalogSeeder;
use Database\Seeders\ProductCategorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(ProductCategorySeeder::class);
});

it('seeds 69 made-to-order lenses with tier-floored prices', function () {
    $this->seed(ProductCatalogSeeder::class);
    $this->seed(ProductCatalogSeeder::class); // idempotent

    expect(Product::where('sku', 'like', 'ML-%')->count())->toBe(69);

    $base = Product::where('sku', 'ML-001')->first(); // 1.56 Sin Filtro, cost 6000
    expect($base->cost)->toBe(6000)
        ->and($base->price)->toBe(125000)       // max(24000, 125000)
        ->and($base->is_stockable)->toBeFalse()
        ->and($base->specs['filter'])->toBe('Sin Filtro');

    expect(Product::where('sku', 'ML-002')->value('price'))->toBe(195000)  // blue floor
        ->and(Product::where('sku', 'ML-003')->value('price'))->toBe(295000) // foto blue floor
        ->and(Product::where('sku', 'ML-069')->value('price'))->toBe(2228000); // 557000*4
});

it('computes lensPrice with the tier floor', function () {
    expect(ProductCatalogSeeder::lensPrice(6000, 'Sin Filtro'))->toBe(125000)
        ->and(ProductCatalogSeeder::lensPrice(50000, 'Foto Blue Cut'))->toBe(295000)
        ->and(ProductCatalogSeeder::lensPrice(557000, 'Foto Blue Cut'))->toBe(2228000);
});
