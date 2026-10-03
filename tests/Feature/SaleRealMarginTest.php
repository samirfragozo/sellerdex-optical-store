<?php

use App\Enums\LensOrderStatus;
use App\Enums\RemakeReason;
use App\Enums\RemakeResponsible;
use App\Models\LensOrder;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\User;

beforeEach(fn () => $this->actingAs(User::factory()->admin()->create()));

/** A sale with one 300k lens (cost 100k) and one 50k product (cost 20k), recalculated. */
function marginSale(): Sale
{
    $sale = Sale::factory()->create(['discount_percent' => 0, 'surcharge_percent' => 0]);
    SaleItem::factory()->create(['sale_id' => $sale->id, 'quantity' => 1, 'unit_price' => 300_000, 'unit_cost' => 100_000, 'tax_rate' => 0]);
    SaleItem::factory()->create(['sale_id' => $sale->id, 'quantity' => 2, 'unit_price' => 25_000, 'unit_cost' => 10_000, 'tax_rate' => 0]);
    $sale->recalculateTotals();

    return $sale->fresh();
}

it('is the net revenue minus the cost of what was sold', function () {
    expect(marginSale()->realMargin())->toBe(350_000 - 100_000 - 20_000);
});

it('drops by the cost of a remake the store is responsible for, and only that', function () {
    $sale = marginSale();
    $lensItem = $sale->items()->where('unit_price', 300_000)->sole();
    $order = LensOrder::factory()->create(['sale_item_id' => $lensItem->id, 'lab_status' => LensOrderStatus::Ready]);

    $order->remake(RemakeReason::Measurements, RemakeResponsible::Store, 90_000);
    expect($sale->realMargin())->toBe(230_000 - 90_000);

    $order->remakes()->sole()->update(['lab_status' => LensOrderStatus::Ready]);
    $order->remakes()->sole()->remake(RemakeReason::LabDefect, RemakeResponsible::Lab, 90_000);
    expect($sale->realMargin())->toBe(230_000 - 90_000);
});

it('excludes the taxes and the payment surcharge from revenue', function () {
    $sale = marginSale();
    $sale->forceFill(['tax_amount' => 10_000, 'total' => 360_000])->saveQuietly();

    expect($sale->realMargin())->toBe(350_000 - 10_000 - 120_000);
});
