<?php

use App\Filament\Resources\ProductCategories\ProductCategoryResource;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('el admin ve el listado de categorías de producto', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->get(ProductCategoryResource::getUrl())
        ->assertSuccessful();
});

it('el vendedor no puede acceder al listado de categorías de producto', function () {
    $seller = User::factory()->seller()->create();

    $this->actingAs($seller)
        ->get(ProductCategoryResource::getUrl())
        ->assertForbidden();
});
