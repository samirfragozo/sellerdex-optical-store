<?php

use App\Filament\Resources\ProductCategories\Pages\EditProductCategory;
use App\Filament\Resources\ProductCategories\ProductCategoryResource;
use App\Models\ProductCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

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

it('permite editar los flags de regla de una categoría', function () {
    $this->actingAs(User::factory()->admin()->create());
    $category = ProductCategory::factory()->create(['key' => 'watch', 'name' => 'Relojes']);

    Livewire::test(EditProductCategory::class, ['record' => $category->getRouteKey()])
        ->fillForm(['requires_prescription' => true])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($category->refresh()->requires_prescription)->toBeTrue();
});

it('el campo key es de solo lectura para categorías de sistema', function () {
    $this->actingAs(User::factory()->admin()->create());
    $category = ProductCategory::factory()->create(['key' => 'lentes', 'name' => 'Lentes', 'is_system' => true]);

    Livewire::test(EditProductCategory::class, ['record' => $category->getRouteKey()])
        ->assertFormFieldIsDisabled('key');
});
