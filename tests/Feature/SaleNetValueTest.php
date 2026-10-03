<?php

use App\Enums\LensOrderStatus;
use App\Enums\RemakeReason;
use App\Enums\RemakeResponsible;
use App\Enums\SaleReturnType;
use App\Enums\SaleStatus;
use App\Models\LensOrder;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\SaleItemLensConfig;
use App\Models\SaleReturn;
use App\Models\SaleReturnItem;
use App\Models\User;

beforeEach(fn () => $this->actingAs(User::factory()->admin()->create()));

it('nets returns out of the balance and the status', function () {
    $sale = Sale::factory()->create(['discount_percent' => 0, 'surcharge_percent' => 0]);
    SaleItem::factory()->create(['sale_id' => $sale->id, 'quantity' => 1, 'unit_price' => 100_000, 'tax_rate' => 0]);
    $cash = PaymentMethod::factory()->create();
    Payment::factory()->create(['sale_id' => $sale->id, 'payment_method_id' => $cash->id, 'amount' => 70_000]);

    expect($sale->fresh()->total)->toBe(100_000)
        ->and($sale->fresh()->balance)->toBe(30_000)
        ->and(Sale::outstanding()->whereKey($sale->id)->exists())->toBeTrue();

    SaleReturn::factory()->create(['sale_id' => $sale->id, 'type' => SaleReturnType::ValueAdjustment, 'total' => 30_000]);
    $sale->fresh()->recalculateStatus();

    $sale = $sale->fresh();
    expect($sale->netTotal())->toBe(70_000)
        ->and($sale->balance)->toBe(0)
        ->and($sale->status)->toBe(SaleStatus::Paid)
        ->and(Sale::outstanding()->whereKey($sale->id)->exists())->toBeFalse();
});

it('does not net a void into the value and has no balance once voided', function () {
    $sale = Sale::factory()->create(['discount_percent' => 0, 'surcharge_percent' => 0]);
    SaleItem::factory()->create(['sale_id' => $sale->id, 'quantity' => 1, 'unit_price' => 50_000, 'tax_rate' => 0]);
    expect($sale->fresh()->balance)->toBe(50_000);

    SaleReturn::factory()->create(['sale_id' => $sale->id, 'type' => SaleReturnType::Void, 'total' => 50_000]);
    $sale->update(['status' => SaleStatus::Voided]);

    expect($sale->fresh()->returnedTotal())->toBe(0)
        ->and($sale->fresh()->balance)->toBe(0);
});

it('tracks the returned and returnable quantity of an item', function () {
    $sale = Sale::factory()->create();
    $item = SaleItem::factory()->create(['sale_id' => $sale->id, 'quantity' => 3, 'unit_price' => 10_000]);
    expect($item->returnableQuantity())->toBe(3);

    $return = SaleReturn::factory()->create(['sale_id' => $sale->id]);
    SaleReturnItem::factory()->create(['sale_return_id' => $return->id, 'sale_item_id' => $item->id, 'quantity' => 2]);

    expect($item->returnedQuantity())->toBe(2)
        ->and($item->returnableQuantity())->toBe(1);
});

it('has no pending lens work for a cancelled order or a fully returned lens item', function () {
    $sale = Sale::factory()->create();
    $item = SaleItem::factory()->create(['sale_id' => $sale->id, 'quantity' => 1, 'unit_price' => 10_000]);
    SaleItemLensConfig::factory()->create(['sale_item_id' => $item->id]);
    $order = LensOrder::factory()->create(['sale_item_id' => $item->id]);

    expect($sale->fresh()->hasPendingLensWork())->toBeTrue()
        ->and(LensOrder::pending()->whereKey($order->id)->exists())->toBeTrue();

    $order->update(['lab_status' => LensOrderStatus::Cancelled]);

    expect($sale->fresh()->hasPendingLensWork())->toBeFalse()
        ->and(LensOrder::pending()->whereKey($order->id)->exists())->toBeFalse();

    $order->update(['lab_status' => LensOrderStatus::Sent]);
    expect($sale->fresh()->hasPendingLensWork())->toBeTrue();

    $return = SaleReturn::factory()->create(['sale_id' => $sale->id]);
    SaleReturnItem::factory()->create(['sale_return_id' => $return->id, 'sale_item_id' => $item->id, 'quantity' => 1]);
    expect($sale->fresh()->hasPendingLensWork())->toBeFalse();
});

it('leaves a voided unpaid sale out of the outstanding sales', function () {
    $sale = Sale::factory()->create(['discount_percent' => 0, 'surcharge_percent' => 0]);
    SaleItem::factory()->create(['sale_id' => $sale->id, 'quantity' => 1, 'unit_price' => 50_000, 'tax_rate' => 0]);
    expect(Sale::outstanding()->whereKey($sale->id)->exists())->toBeTrue();

    $sale->update(['status' => SaleStatus::Voided]);

    expect(Sale::outstanding()->whereKey($sale->id)->exists())->toBeFalse();
});

it('cannot remake a cancelled lens order', function () {
    $item = SaleItem::factory()->create();
    $order = LensOrder::factory()->create(['sale_item_id' => $item->id, 'lab_status' => LensOrderStatus::Cancelled]);

    expect(fn () => $order->remake(RemakeReason::Measurements, RemakeResponsible::Store, 1_000))->toThrow(DomainException::class);
});

it('marks a fully returned sale as paid when nothing is owed', function () {
    $sale = Sale::factory()->create(['discount_percent' => 0, 'surcharge_percent' => 0]);
    SaleItem::factory()->create(['sale_id' => $sale->id, 'quantity' => 1, 'unit_price' => 50_000, 'tax_rate' => 0]);
    Payment::factory()->create(['sale_id' => $sale->id, 'payment_method_id' => PaymentMethod::factory()->create()->id, 'amount' => 50_000]);
    SaleReturn::factory()->create(['sale_id' => $sale->id, 'type' => SaleReturnType::Return, 'total' => 50_000]);

    $sale->fresh()->recalculateStatus();

    expect($sale->fresh()->netTotal())->toBe(0)
        ->and($sale->fresh()->status)->toBe(SaleStatus::Paid);
});
