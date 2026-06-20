<?php

use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('calcula el margen', function () {
    $product = Product::factory()->create(['price' => 100_000, 'cost' => 60_000]);
    expect($product->margin())->toBe(40_000);
});

it('pertenece a una categoría de producto', function () {
    $category = ProductCategory::factory()->create(['name' => 'Montura']);
    $product = Product::factory()->create(['product_category_id' => $category->id]);
    expect($product->category)->toBeInstanceOf(ProductCategory::class)
        ->and($product->category->name)->toBe('Montura');
});

it('el vendedor solo ve el catálogo, no lo modifica', function () {
    $seller = User::factory()->seller()->create();
    expect($seller->can('ViewAny:Product'))->toBeTrue()
        ->and($seller->can('Create:Product'))->toBeFalse()
        ->and($seller->can('Delete:Product'))->toBeFalse();
});

it('el admin gestiona el catálogo', function () {
    $admin = User::factory()->admin()->create();
    expect($admin->can('Create:Product'))->toBeTrue()
        ->and($admin->can('Delete:Product'))->toBeTrue();
});
