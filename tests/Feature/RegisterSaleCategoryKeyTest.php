<?php

use App\Actions\RegisterSale;
use App\Models\Customer;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\User;
use Database\Seeders\ProductCatalogSeeder;
use Database\Seeders\ProductCategorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('compone el combo de lente aunque la categoría haya sido renombrada', function () {
    $this->seed(ProductCategorySeeder::class);
    $this->seed(ProductCatalogSeeder::class);

    // Renombrar el label visible NO debe romper la lógica (que ahora usa key='lens').
    ProductCategory::keyed('lens')->update(['name' => 'Lentes oftálmicos']);

    $lens = Product::whereHas('category', fn ($q) => $q->where('key', 'lens'))->first();
    expect($lens)->not->toBeNull();

    $seller = User::factory()->seller()->create();
    $customer = Customer::factory()->create();

    $sale = app(RegisterSale::class)->handle([
        'customer_id' => $customer->id,
        'document_type' => 'order',
        'items' => [
            ['product_id' => $lens->id, 'description' => $lens->name, 'quantity' => 1, 'unit_price' => $lens->price, 'unit_cost' => $lens->cost],
        ],
        'combo' => ['forro' => 'small', 'include_liquid' => false, 'with_exam' => false],
    ], $seller);

    // El combo agrega líneas $0 (paño, bolsa) sólo si reconoció el lente por key.
    expect($sale->items->count())->toBeGreaterThan(1);
});
