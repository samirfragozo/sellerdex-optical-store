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

    $frame = Product::where('sku', 'MNT-COMPLETAS-ACETATO')->first();
    $frame->update(['stock' => 5]);

    $sale = app(RegisterSale::class)->handle([
        'customer_id' => Customer::factory()->create()->id,
        'document_type' => 'order',
        'items' => [
            ['product_id' => $frame->id, 'description' => $frame->name, 'quantity' => 1, 'unit_price' => 0, 'unit_cost' => $frame->cost],
        ],
    ], User::factory()->seller()->create());

    expect($sale->items)->toHaveCount(1)
        ->and($frame->fresh()->stock)->toBe(4);
});
