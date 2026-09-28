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

    // No combo slots are installed, so the frame is the only line.
    expect($sale->items)->toHaveCount(1)
        ->and($frame->fresh()->stock)->toBe(4);
});
