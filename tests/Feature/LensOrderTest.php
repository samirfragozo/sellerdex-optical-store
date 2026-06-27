<?php

use App\Enums\LensOrderStatus;
use App\Models\LensOrder;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\Supplier;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function lensSaleItem(): SaleItem
{
    $category = ProductCategory::factory()->create(['key' => 'lens', 'generates_lab_order' => true]);
    $product = Product::factory()->create(['product_category_id' => $category->id]);
    $sale = Sale::factory()->create();

    return SaleItem::factory()->create(['sale_id' => $sale->id, 'product_id' => $product->id]);
}

it('crea una orden de laboratorio ligada al ítem de lente y al laboratorio', function () {
    $item = lensSaleItem();
    $lab = Supplier::factory()->laboratory()->create();

    $order = LensOrder::factory()->create([
        'sale_item_id' => $item->id,
        'supplier_id' => $lab->id,
    ]);

    expect($order->lab_status)->toBe(LensOrderStatus::Sent)
        ->and($order->saleItem->is($item))->toBeTrue()
        ->and($order->supplier->is($lab))->toBeTrue();
});

it('reconoce el ítem de lente y filtra órdenes pendientes', function () {
    $item = lensSaleItem();
    LensOrder::factory()->create(['sale_item_id' => $item->id, 'lab_status' => LensOrderStatus::Sent->value]);

    $received = LensOrder::factory()->create(['lab_status' => LensOrderStatus::Received->value]);

    expect($item->isLens())->toBeTrue()
        ->and(LensOrder::pending()->count())->toBe(1)
        ->and($received->isReceived())->toBeTrue();
});
