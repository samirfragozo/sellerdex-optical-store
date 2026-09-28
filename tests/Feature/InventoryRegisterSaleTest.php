<?php

use App\Actions\RegisterSale;
use App\Models\Customer;
use App\Models\Product;
use App\Models\User;
use Database\Seeders\ProductCatalogSeeder;
use Database\Seeders\ProductCategorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('decrements a real frame stock when sold via RegisterSale', function () {
    $this->seed(ProductCategorySeeder::class);
    $this->seed(ProductCatalogSeeder::class);

    $frame = Product::where('sku', 'MNT-COMPLETA-ACETATO')->first();
    $frame->update(['stock' => 5]);

    $sale = app(RegisterSale::class)->handle([
        'customer_id' => Customer::factory()->create()->id,
        'document_type' => 'order',
        'products' => [
            ['product_id' => $frame->id, 'description' => $frame->name, 'quantity' => 1, 'unit_price' => 0],
        ],
    ], User::factory()->seller()->create());

    // A standalone frame sale auto-includes a funda and a bag, so three lines; the frame stock still drops by 1.
    expect($sale->items)->toHaveCount(3)
        ->and($frame->fresh()->stock)->toBe(4)
        ->and($sale->items->contains(fn ($i) => Product::find($i->product_id)?->sku === 'ACC-FUNDA'))->toBeTrue();
});
