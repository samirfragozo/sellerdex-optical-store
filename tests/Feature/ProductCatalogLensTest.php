<?php

use App\Models\Product;
use App\Support\LensPricing;
use Database\Seeders\ProductCatalogSeeder;
use Database\Seeders\ProductCategorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(ProductCategorySeeder::class);
});

it('seeds one base lens product per design, priced at the Sin Filtro floor', function () {
    $this->seed(ProductCatalogSeeder::class);
    $this->seed(ProductCatalogSeeder::class); // idempotent

    $monofocal = Product::where('sku', 'ML-MONOFOCAL')->first();
    $bifocal = Product::where('sku', 'ML-BIFOCAL')->first();
    $progresivo = Product::where('sku', 'ML-PROGRESIVO')->first();

    expect($monofocal->cost)->toBe(6000)
        ->and($monofocal->price)->toBe(125000) // floored, cost×4 = 24000
        ->and($monofocal->is_stockable)->toBeFalse()
        ->and($monofocal->specs['design'])->toBe('Monofocal')
        ->and($bifocal->cost)->toBe(8000)
        ->and($bifocal->price)->toBe(125000)
        ->and($progresivo->cost)->toBe(30000)
        ->and($progresivo->price)->toBe(125000);
});

it('computes LensPricing::price with the tier floor', function () {
    expect(LensPricing::price(6000, 'Sin Filtro'))->toBe(125000)
        ->and(LensPricing::price(50000, 'Foto Blue Cut'))->toBe(295000)
        ->and(LensPricing::price(557000, 'Foto Blue Cut'))->toBe(2228000)
        ->and(LensPricing::price(6000, null))->toBe(24000); // no floor for an unknown/missing filter
});
