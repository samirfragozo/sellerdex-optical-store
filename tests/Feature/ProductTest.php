<?php

use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\User;
use App\Support\PermissionsTeam;
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
    expect(PermissionsTeam::runAs($seller->company, fn () => $seller->can('ViewAny:Product')))->toBeTrue()
        ->and(PermissionsTeam::runAs($seller->company, fn () => $seller->can('Create:Product')))->toBeFalse()
        ->and(PermissionsTeam::runAs($seller->company, fn () => $seller->can('Delete:Product')))->toBeFalse();
});

it('el admin gestiona el catálogo', function () {
    $admin = User::factory()->admin()->create();
    expect(PermissionsTeam::runAs($admin->company, fn () => $admin->can('Create:Product')))->toBeTrue()
        ->and(PermissionsTeam::runAs($admin->company, fn () => $admin->can('Delete:Product')))->toBeTrue();
});

it('defaults tax_rate to zero and casts it as decimal', function () {
    $product = Product::factory()->create();

    expect($product->fresh()->tax_rate)->toBe('0.00');
});

it('accepts a positive tax_rate', function () {
    $product = Product::factory()->create(['tax_rate' => 19]);

    expect($product->fresh()->tax_rate)->toBe('19.00');
});
