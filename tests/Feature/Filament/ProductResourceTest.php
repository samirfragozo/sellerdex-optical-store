<?php

use App\Filament\Resources\Products\ProductResource;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('el admin ve el listado de productos', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->get(ProductResource::getUrl())
        ->assertSuccessful();
});

it('el vendedor también puede ver el listado de productos', function () {
    $seller = User::factory()->seller()->create();

    $this->actingAs($seller)
        ->get(ProductResource::getUrl())
        ->assertSuccessful();
});
