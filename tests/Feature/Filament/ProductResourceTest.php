<?php

use App\Enums\VatRegime;
use App\Filament\Resources\Products\Pages\CreateProduct;
use App\Filament\Resources\Products\ProductResource;
use App\Models\ProductCategory;
use App\Models\Tax;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

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

it('suggests the category default tax without overwriting a chosen one', function () {
    $admin = User::factory()->admin()->create();
    $this->actingAs($admin);
    $iva = Tax::factory()->create(['rate' => 19]);
    $other = Tax::factory()->create(['rate' => 5]);
    $category = ProductCategory::factory()->create(['default_tax_id' => $iva->id]);

    Livewire::test(CreateProduct::class)
        ->fillForm(['product_category_id' => $category->id])
        ->assertSchemaStateSet(['tax_id' => $iva->id])
        ->fillForm(['tax_id' => $other->id, 'product_category_id' => null])
        ->fillForm(['product_category_id' => $category->id])
        ->assertSchemaStateSet(['tax_id' => $other->id]);
});

it('hides the tax selector from companies not responsible for VAT', function () {
    $admin = User::factory()->admin()->create();
    $admin->company->update(['vat_regime' => VatRegime::NotResponsible]);

    $this->actingAs($admin);

    Livewire::test(CreateProduct::class)->assertFormFieldHidden('tax_id');
});
