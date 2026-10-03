<?php

use App\Enums\LensOrderStatus;
use App\Models\LensOrder;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\SaleItemLensConfig;
use App\Models\Supplier;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/** A lens line is identified by its resolved lens configuration snapshot, not by a lens-category product. */
function lensSaleItem(): SaleItem
{
    $sale = Sale::factory()->create();
    $item = SaleItem::factory()->create(['sale_id' => $sale->id, 'product_id' => null]);
    SaleItemLensConfig::factory()->create(['sale_item_id' => $item->id]);

    return $item;
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

    $ready = LensOrder::factory()->ready()->create();

    expect($item->isLens())->toBeTrue()
        ->and(LensOrder::pending()->count())->toBe(1)
        ->and($ready->isReady())->toBeTrue();
});
