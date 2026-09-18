<?php

use App\Enums\LensOrderStatus;
use App\Exceptions\PendingLensOrderException;
use App\Models\LensOrder;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\SaleItemLensConfig;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * A lens line is now identified by its resolved lens configuration snapshot
 * (SaleItem::isLens()), not by a lens-category product.
 *
 * @return array{0: Sale, 1: SaleItem}
 */
function saleWithLens(): array
{
    $sale = Sale::factory()->create(['is_delivered' => false]);
    $item = SaleItem::factory()->create(['sale_id' => $sale->id, 'product_id' => null]);
    SaleItemLensConfig::factory()->create(['sale_item_id' => $item->id]);

    return [$sale, $item];
}

it('bloquea la entrega si el lente no tiene orden recibida', function () {
    [$sale, $item] = saleWithLens();
    // No lens order at all → strict rule blocks.

    expect($sale->canBeDelivered())->toBeFalse()
        ->and(fn () => $sale->update(['is_delivered' => true]))
        ->toThrow(PendingLensOrderException::class);

    expect($sale->fresh()->is_delivered)->toBeFalse();
});

it('bloquea la entrega si la orden existe pero no está recibida', function () {
    [$sale, $item] = saleWithLens();
    LensOrder::factory()->create(['sale_item_id' => $item->id, 'lab_status' => LensOrderStatus::InProcess->value]);

    expect($sale->canBeDelivered())->toBeFalse()
        ->and(fn () => $sale->update(['is_delivered' => true]))
        ->toThrow(PendingLensOrderException::class);

    expect($sale->fresh()->is_delivered)->toBeFalse();
});

it('permite la entrega cuando todos los lentes están recibidos', function () {
    [$sale, $item] = saleWithLens();
    LensOrder::factory()->received()->create(['sale_item_id' => $item->id]);

    expect($sale->canBeDelivered())->toBeTrue();

    $sale->update(['is_delivered' => true]);

    expect($sale->fresh()->is_delivered)->toBeTrue();
});

it('no afecta a ventas sin lentes', function () {
    $product = Product::factory()->create(['is_stockable' => false]);
    $sale = Sale::factory()->create(['is_delivered' => false]);
    SaleItem::factory()->create(['sale_id' => $sale->id, 'product_id' => $product->id]);

    expect($sale->canBeDelivered())->toBeTrue();

    $sale->update(['is_delivered' => true]);

    expect($sale->fresh()->is_delivered)->toBeTrue();
});
