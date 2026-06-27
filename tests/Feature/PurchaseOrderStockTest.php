<?php

use App\Enums\PurchaseOrderStatus;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function poStockableProduct(int $stock = 5): Product
{
    return Product::factory()->create(['is_stockable' => true, 'stock' => $stock]);
}

it('repone stock al recibir la orden', function () {
    $product = poStockableProduct(5);
    $order = PurchaseOrder::factory()->create();
    PurchaseOrderItem::factory()->create([
        'purchase_order_id' => $order->id, 'product_id' => $product->id, 'quantity' => 8, 'unit_cost' => 1000,
    ]);

    $order->receive();

    expect($order->fresh()->status)->toBe(PurchaseOrderStatus::Received)
        ->and($order->fresh()->received_at)->not->toBeNull()
        ->and($product->fresh()->stock)->toBe(13);
});

it('no repone dos veces si ya está recibida', function () {
    $product = poStockableProduct(5);
    $order = PurchaseOrder::factory()->create();
    PurchaseOrderItem::factory()->create([
        'purchase_order_id' => $order->id, 'product_id' => $product->id, 'quantity' => 8, 'unit_cost' => 1000,
    ]);

    $order->receive();
    $order->receive();

    expect($product->fresh()->stock)->toBe(13);
});

it('revierte el stock al cancelar una orden recibida', function () {
    $product = poStockableProduct(5);
    $order = PurchaseOrder::factory()->create();
    PurchaseOrderItem::factory()->create([
        'purchase_order_id' => $order->id, 'product_id' => $product->id, 'quantity' => 8, 'unit_cost' => 1000,
    ]);

    $order->receive();
    $order->cancel();

    expect($order->fresh()->status)->toBe(PurchaseOrderStatus::Cancelled)
        ->and($product->fresh()->stock)->toBe(5);
});

it('no descuenta de más si se cancela dos veces', function () {
    $product = poStockableProduct(5);
    $order = PurchaseOrder::factory()->create();
    PurchaseOrderItem::factory()->create([
        'purchase_order_id' => $order->id, 'product_id' => $product->id, 'quantity' => 8, 'unit_cost' => 1000,
    ]);

    $order->receive();
    $order->cancel();
    $order->cancel();

    expect($product->fresh()->stock)->toBe(5);
});

it('no mueve stock de productos no inventariables', function () {
    $product = Product::factory()->create(['is_stockable' => false, 'stock' => null]);
    $order = PurchaseOrder::factory()->create();
    PurchaseOrderItem::factory()->create([
        'purchase_order_id' => $order->id, 'product_id' => $product->id, 'quantity' => 8, 'unit_cost' => 1000,
    ]);

    $order->receive();

    expect($product->fresh()->stock)->toBeNull();
});
