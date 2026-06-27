<?php

use App\Enums\PurchaseOrderStatus;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\Supplier;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('crea una orden de compra con su proveedor y estado inicial', function () {
    $order = PurchaseOrder::factory()->create(['supplier_id' => Supplier::factory()]);

    expect($order->status)->toBe(PurchaseOrderStatus::Draft)
        ->and($order->supplier)->toBeInstanceOf(Supplier::class);
});

it('calcula el subtotal del ítem y el total de la orden', function () {
    $order = PurchaseOrder::factory()->create();
    $product = Product::factory()->create();

    PurchaseOrderItem::factory()->create([
        'purchase_order_id' => $order->id,
        'product_id' => $product->id,
        'quantity' => 3,
        'unit_cost' => 20000,
    ]);
    PurchaseOrderItem::factory()->create([
        'purchase_order_id' => $order->id,
        'product_id' => Product::factory(),
        'quantity' => 2,
        'unit_cost' => 5000,
    ]);

    $item = $order->items()->first();

    expect($item->subtotal)->toBe(60000)
        ->and($order->fresh()->total)->toBe(70000);
});
