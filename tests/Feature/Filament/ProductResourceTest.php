<?php

use App\Enums\VatRegime;
use App\Filament\Resources\Products\Pages\CreateProduct;
use App\Filament\Resources\Products\Pages\EditProduct;
use App\Filament\Resources\Products\ProductResource;
use App\Models\Product;
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

it('does not suggest a deactivated category tax', function () {
    $this->actingAs(User::factory()->admin()->create());
    $inactive = Tax::factory()->create(['is_active' => false]);
    $category = ProductCategory::factory()->create(['default_tax_id' => $inactive->id]);

    Livewire::test(CreateProduct::class)
        ->fillForm(['product_category_id' => $category->id])
        ->assertSchemaStateSet(['tax_id' => null]);
});

it('keeps a deactivated tax on a product that uses it when saving other changes', function () {
    $this->actingAs(User::factory()->admin()->create());
    $tax = Tax::factory()->create(['rate' => 19]);
    $product = Product::factory()->create(['tax_id' => $tax->id]);
    $tax->update(['is_active' => false]);

    Livewire::test(EditProduct::class, ['record' => $product->getRouteKey()])
        ->assertSchemaStateSet(['tax_id' => $tax->id])
        ->fillForm(['price' => 123_000])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($product->fresh())->price->toBe(123_000)->tax_id->toBe($tax->id);
});
