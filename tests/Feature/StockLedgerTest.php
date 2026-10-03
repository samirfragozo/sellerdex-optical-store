<?php

use App\Enums\StockMovementType;
use App\Models\Company;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\StockMovement;
use App\Models\User;
use App\Support\StockLedger;

beforeEach(function () {
    $this->user = User::factory()->admin()->create();
    $this->actingAs($this->user);
    $this->product = Product::factory()->create(['is_stockable' => true, 'stock' => 10]);

    // Fail loudly on a bad setup.
    expect($this->product->fresh()->stock)->toBe(10)->and(StockMovement::count())->toBe(0);
});

it('records a movement with the running balance and moves the cached stock', function () {
    $first = StockLedger::record($this->product, StockMovementType::Adjustment, -3, reason: 'Rotura');
    $second = StockLedger::record($this->product, StockMovementType::Purchase, 5);

    expect($first->balance_after)->toBe(7)
        ->and($first->reason)->toBe('Rotura')
        ->and($first->user_id)->toBe($this->user->id)
        ->and($second->balance_after)->toBe(12)
        ->and($this->product->fresh()->stock)->toBe(12);
});

it('allows the stock to go negative', function () {
    StockLedger::record($this->product, StockMovementType::Sale, -11);

    expect($this->product->fresh()->stock)->toBe(-1);
});

it('starts from zero when a stockable product has no stock yet', function () {
    $product = Product::factory()->create(['is_stockable' => true, 'stock' => null]);

    expect(StockLedger::record($product, StockMovementType::Initial, 4)->balance_after)->toBe(4);
});

it('writes nothing for a non-stockable product or a company that does not track inventory', function () {
    $service = Product::factory()->create(['is_stockable' => false, 'stock' => null]);
    expect(StockLedger::record($service, StockMovementType::Sale, -1))->toBeNull();

    $this->user->company->update(['tracks_inventory' => false]);
    expect(StockLedger::record($this->product->fresh(), StockMovementType::Sale, -1))->toBeNull()
        ->and($this->product->fresh()->stock)->toBe(10)
        ->and(StockMovement::count())->toBe(0);
});

it('treats an undecided company (null) as not tracking inventory', function () {
    $this->user->company->update(['tracks_inventory' => null]);

    expect(StockLedger::record($this->product->fresh(), StockMovementType::Sale, -1))->toBeNull()
        ->and($this->product->fresh()->stock)->toBe(10);
});

it('records sale lines, quantity changes, deletions and voids as sale movements tied to their source', function () {
    $sale = Sale::factory()->create();
    $item = SaleItem::factory()->create(['sale_id' => $sale->id, 'product_id' => $this->product->id, 'quantity' => 2]);
    $item->update(['quantity' => 3]);
    $item->delete();

    $movements = StockMovement::orderBy('id')->get();
    expect($movements->pluck('quantity')->all())->toBe([-2, -1, 3])
        ->and($movements->pluck('type')->unique()->all())->toBe([StockMovementType::Sale])
        ->and($movements->first()->source_id)->toBe($item->id)
        ->and($this->product->fresh()->stock)->toBe(10);
});

it('records a void and unvoid as sale movements tied to the sale', function () {
    $sale = Sale::factory()->create();
    SaleItem::factory()->create(['sale_id' => $sale->id, 'product_id' => $this->product->id, 'quantity' => 2]);

    $sale->update(['status' => 'voided']);

    $void = StockMovement::orderBy('id')->get()->last();
    expect($void->quantity)->toBe(2)
        ->and($void->type)->toBe(StockMovementType::Sale)
        ->and($void->source->is($sale))->toBeTrue()
        ->and($this->product->fresh()->stock)->toBe(10);
});

it('records purchase receipts and cancellations as purchase movements', function () {
    $order = PurchaseOrder::factory()->create();
    PurchaseOrderItem::factory()->create(['purchase_order_id' => $order->id, 'product_id' => $this->product->id, 'quantity' => 4]);

    $order->receive();
    $order->fresh()->cancel();

    expect(StockMovement::where('type', StockMovementType::Purchase)->orderBy('id')->pluck('quantity')->all())->toBe([4, -4])
        ->and(StockMovement::first()->source->is($order))->toBeTrue()
        ->and($this->product->fresh()->stock)->toBe(10);
});

it('keeps purchases cost-only when the company does not track inventory', function () {
    $this->user->company->update(['tracks_inventory' => false]);
    $order = PurchaseOrder::factory()->create();
    PurchaseOrderItem::factory()->create(['purchase_order_id' => $order->id, 'product_id' => $this->product->id, 'quantity' => 4]);

    $order->receive();

    expect($this->product->fresh()->stock)->toBe(10)->and(StockMovement::count())->toBe(0);
});

it('does not let another company see a movement', function () {
    StockLedger::record($this->product, StockMovementType::Adjustment, 1, reason: 'x');
    $this->actingAs(User::factory()->admin()->for(Company::factory()->create())->create());

    expect(StockMovement::count())->toBe(0);
});
