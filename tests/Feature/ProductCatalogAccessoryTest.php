<?php

use App\Models\Product;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\ProductCatalogSeeder;
use Database\Seeders\ProductCategorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(fn () => $this->seed(ProductCategorySeeder::class));

it('seeds consumables with real costs and prices', function () {
    $this->seed(ProductCatalogSeeder::class);

    expect(Product::where('sku', 'ACC-FORRO-SMALL')->first())->cost->toBe(2900)->price->toBe(10000)
        ->and(Product::where('sku', 'ACC-FORRO-LARGE')->first())->cost->toBe(4000)->price->toBe(15000)
        ->and(Product::where('sku', 'ACC-PANO')->first())->cost->toBe(600)->price->toBe(2000)
        ->and(Product::where('sku', 'ACC-LIQUIDO')->first())->cost->toBe(2000)->price->toBe(8000)
        ->and(Product::where('sku', 'ACC-BOLSA-PAPEL')->first())->cost->toBe(1000)->price->toBe(0)
        ->and(Product::where('sku', 'ACC-FUNDA')->first())->cost->toBe(500)->price->toBe(0);
});

it('seeds contact lenses with includes bundle and services', function () {
    $this->seed(ProductCatalogSeeder::class);

    $box = Product::where('sku', 'ACC-LC-AIROPTIX-CYL-X3')->first();
    expect($box->cost)->toBe(219000)->and($box->price)->toBe(450000)
        ->and($box->specs['includes'])->toContain('ACC-SOLUCION-LC');

    expect(Product::where('sku', 'SRV-EXAMEN')->first())->price->toBe(35000)
        ->and(Product::where('sku', 'SRV-REPARACION-PATICA')->first())->cost->toBe(10000)->price->toBe(25000);
    // Optacril/Hialub removed
    expect(Product::where('sku', 'ACC-GOTAS-OPTACRIL')->exists())->toBeFalse();
});

it('runs from DatabaseSeeder', function () {
    $this->seed(DatabaseSeeder::class);
    expect(Product::where('sku', 'ML-001')->exists())->toBeTrue();
});
