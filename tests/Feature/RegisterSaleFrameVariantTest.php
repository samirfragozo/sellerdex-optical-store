<?php

use App\Actions\RegisterSale;
use App\Models\Customer;
use App\Models\Option;
use App\Models\OptionGroup;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\User;

it('sells a frame variant and decrements its own stock, not the base or a sibling', function () {
    $lensCategory = ProductCategory::factory()->create(['key' => 'lens']);
    $lens = Product::factory()->create(['product_category_id' => $lensCategory->id, 'price' => 100000, 'cost' => 40000, 'is_stockable' => false]);

    $frameCategory = ProductCategory::factory()->create(['key' => 'frame']);
    $base = Product::factory()->create(['product_category_id' => $frameCategory->id, 'is_stockable' => false, 'stock' => null]);

    $structure = OptionGroup::factory()->create(['name' => 'Estructura']);
    $completas = Option::factory()->for($structure, 'group')->create(['name' => 'Completas']);
    $base->optionGroups()->attach($structure->id);

    $variant = Product::factory()->create([
        'product_category_id' => $frameCategory->id,
        'base_product_id' => $base->id,
        'price' => 90000,
        'cost' => 40000,
        'is_stockable' => true,
        'stock' => 5,
    ]);
    $variant->variantOptions()->attach($completas->id);

    $sibling = Product::factory()->create([
        'product_category_id' => $frameCategory->id,
        'base_product_id' => $base->id,
        'is_stockable' => true,
        'stock' => 5,
    ]);

    $customer = Customer::factory()->create();
    $seller = User::factory()->seller()->create();

    app(RegisterSale::class)->handle([
        'customer_id' => $customer->id,
        'document_type' => 'order',
        'armados' => [[
            'lens' => [
                'product_id' => $lens->id,
                'description' => $lens->name,
                'unit_price' => $lens->price,
                'unit_cost' => $lens->cost,
            ],
            'own_frame' => false,
            'frame' => [
                'product_id' => $variant->id,
                'description' => $variant->name,
                'unit_price' => $variant->price,
                'unit_cost' => $variant->cost,
            ],
        ]],
    ], $seller);

    expect($variant->fresh()->stock)->toBe(4)
        ->and($sibling->fresh()->stock)->toBe(5)
        ->and($base->fresh()->stock)->toBeNull();
});

it('sells a frame variant as a standalone loose product and adds a free funda', function () {
    $frameCategory = ProductCategory::factory()->create(['key' => 'frame']);
    $base = Product::factory()->create(['product_category_id' => $frameCategory->id, 'is_stockable' => false, 'stock' => null]);

    $structure = OptionGroup::factory()->create(['name' => 'Estructura']);
    $completas = Option::factory()->for($structure, 'group')->create(['name' => 'Completas']);
    $base->optionGroups()->attach($structure->id);

    $variant = Product::factory()->create([
        'product_category_id' => $frameCategory->id,
        'base_product_id' => $base->id,
        'name' => 'Montura Completas Pasta',
        'price' => 90000,
        'cost' => 40000,
        'is_stockable' => true,
        'stock' => 5,
    ]);
    $variant->variantOptions()->attach($completas->id);

    $accessoryCategory = ProductCategory::factory()->create(['key' => 'accessory']);
    $funda = Product::factory()->create(['product_category_id' => $accessoryCategory->id, 'sku' => 'ACC-FUNDA', 'price' => 3000]);

    $customer = Customer::factory()->create();
    $seller = User::factory()->seller()->create();

    $sale = app(RegisterSale::class)->handle([
        'customer_id' => $customer->id,
        'document_type' => 'order',
        'products' => [[
            'product_id' => $variant->id,
            'description' => $variant->name,
            'quantity' => 1,
            'unit_price' => $variant->price,
            'unit_cost' => $variant->cost,
        ]],
    ], $seller);

    expect($variant->fresh()->stock)->toBe(4)
        ->and($sale->items->firstWhere('product_id', $variant->id)->unit_price)->toBe(90000)
        ->and($sale->items->firstWhere('product_id', $funda->id))->not->toBeNull();
});
