<?php

use App\Models\Product;
use Database\Seeders\ProductCatalogSeeder;
use Database\Seeders\ProductCategorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(fn () => $this->seed(ProductCategorySeeder::class));

it('seeds 18 frame templates that are stockable with zero stock', function () {
    $this->seed(ProductCatalogSeeder::class);

    $frames = Product::where('sku', 'like', 'MNT-%')->get();
    expect($frames)->toHaveCount(18)
        ->and($frames->every(fn ($f) => $f->is_stockable === true && (int) $f->stock === 0))->toBeTrue();

    $one = Product::where('sku', 'MNT-COMPLETAS-ACETATO')->first();
    expect($one->specs['structure'])->toBe('Completas')
        ->and($one->specs['material'])->toBe('Acetato');

    $semiTr90 = Product::where('sku', 'MNT-SEMI-AL-AIRE-TR-90')->first();
    expect($semiTr90)->not->toBeNull()
        ->and($semiTr90->specs['structure'])->toBe('Semi al Aire')
        ->and($semiTr90->specs['material'])->toBe('TR-90');
});

it('seeds sunglasses with cost and price', function () {
    $this->seed(ProductCatalogSeeder::class);

    expect(Product::where('sku', 'GS-PASTA')->first())
        ->cost->toBe(2500)->price->toBe(25000)
        ->and(Product::where('sku', 'GS-ACETATO')->first())
        ->cost->toBe(22000)->price->toBe(60000);
});
