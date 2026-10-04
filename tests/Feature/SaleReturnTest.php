<?php

use App\Actions\RegisterSaleReturn;
use App\Enums\LensOrderStatus;
use App\Enums\SaleReturnType;
use App\Enums\SaleStatus;
use App\Enums\StockMovementType;
use App\Models\Company;
use App\Models\Customer;
use App\Models\LensOrder;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\SaleItemLensConfig;
use App\Models\SaleReturn;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    $this->admin = User::factory()->admin()->create();
    $this->actingAs($this->admin);
    $this->cash = PaymentMethod::where('is_default', true)->first() ?? PaymentMethod::factory()->create(['is_default' => true, 'name' => 'Efectivo']);
    $this->credit = PaymentMethod::storeCreditFor($this->admin->company_id) ?? PaymentMethod::factory()->create(['name' => 'Saldo a favor', 'is_store_credit' => true]);
    $this->customer = Customer::factory()->create();
    $this->product = Product::factory()->create(['is_stockable' => true, 'stock' => 5]);
    // An order holds stock from the start, so restocking is observable.
    $this->sale = Sale::factory()->create(['customer_id' => $this->customer->id, 'document_type' => 'order', 'discount_percent' => 0, 'surcharge_percent' => 0]);
    $this->line = SaleItem::factory()->create(['sale_id' => $this->sale->id, 'product_id' => $this->product->id, 'quantity' => 2, 'unit_price' => 50_000, 'tax_rate' => 0]);
    Payment::factory()->create(['sale_id' => $this->sale->id, 'payment_method_id' => $this->cash->id, 'amount' => 100_000]);
});

function returnSale(SaleReturnType $type, array $data): SaleReturn
{
    return app(RegisterSaleReturn::class)->handle(test()->sale->fresh(), $type, $data, test()->admin);
}

it('starts from a paid sale of 100 000 holding two units', function () {
    $sale = $this->sale->fresh();

    expect($sale->netTotal())->toBe(100_000)
        ->and($sale->totalPaid())->toBe(100_000)
        ->and($sale->holdsStock())->toBeTrue()
        ->and($this->product->fresh()->stock)->toBe(3);
});

it('records the seller as actor and the admin as approver', function () {
    $seller = User::factory()->seller()->create(['company_id' => $this->sale->company_id]);
    $admin = User::factory()->admin()->create(['company_id' => $this->sale->company_id]);

    $return = app(RegisterSaleReturn::class)->handle($this->sale->fresh(), SaleReturnType::ValueAdjustment, [
        'reason' => 'Ajuste', 'amount' => 1_000, 'refund_amount' => 0, 'store_credit_amount' => 0,
    ], $seller, $admin);

    expect($return->user_id)->toBe($seller->id)->and($return->approved_by)->toBe($admin->id);
});

it('refuses a return approved by a non-admin', function () {
    $seller = User::factory()->seller()->create(['company_id' => $this->sale->company_id]);

    app(RegisterSaleReturn::class)->handle($this->sale->fresh(), SaleReturnType::ValueAdjustment, [
        'reason' => 'Ajuste', 'amount' => 1_000, 'refund_amount' => 0, 'store_credit_amount' => 0,
    ], $seller);
})->throws(ValidationException::class);

it('refuses an approver from another company', function () {
    $outsider = User::factory()->admin()->create();
    $this->actingAs($outsider); // an admin in their own company's team, so only the company check can refuse them

    try {
        app(RegisterSaleReturn::class)->handle($this->sale->fresh(), SaleReturnType::ValueAdjustment, [
            'reason' => 'Ajuste', 'amount' => 1_000, 'refund_amount' => 0, 'store_credit_amount' => 0,
        ], $outsider, $outsider);
    } catch (ValidationException $exception) {
        expect($exception->errors())->toBe(['approval_pin' => [__('app.approval.required')]]);

        return;
    }

    $this->fail('Expected a ValidationException.');
});

it('returns one unit, restocks it and gives store credit', function () {
    $return = returnSale(SaleReturnType::Return, [
        'reason' => 'No le gustó', 'items' => [['sale_item_id' => $this->line->id, 'quantity' => 1, 'restock' => true]],
        'refund_amount' => 0, 'store_credit_amount' => 50_000,
    ]);

    expect($return->total)->toBe(50_000)
        ->and($return->approved_by)->toBe($this->admin->id)
        ->and($return->user_id)->toBe($this->admin->id)
        ->and($return->items()->sole()->quantity)->toBe(1)
        ->and($this->customer->creditBalance())->toBe(50_000)
        ->and($this->sale->fresh()->netTotal())->toBe(50_000)
        ->and($this->sale->fresh()->balance)->toBe(0)
        ->and($this->product->fresh()->stock)->toBe(4)
        ->and(StockMovement::where('type', StockMovementType::SaleReturn)->sole()->quantity)->toBe(1);
});

it('values a returned line after the sale discount', function () {
    $this->sale->update(['discount_percent' => 10]);
    $this->sale->recalculateTotals();

    $return = returnSale(SaleReturnType::Return, [
        'reason' => 'x', 'items' => [['sale_item_id' => $this->line->id, 'quantity' => 1, 'restock' => false]],
        'refund_amount' => 0, 'store_credit_amount' => 0,
    ]);

    expect($return->total)->toBe(45_000)
        ->and($this->product->fresh()->stock)->toBe(3);
});

it('refunds cash only through an open session, lowering the expected cash', function () {
    $data = ['reason' => 'Defecto', 'items' => [['sale_item_id' => $this->line->id, 'quantity' => 1, 'restock' => false]],
        'refund_amount' => 50_000, 'refund_payment_method_id' => $this->cash->id, 'store_credit_amount' => 0];

    expect(fn () => returnSale(SaleReturnType::Return, $data))->toThrow(ValidationException::class, __('app.sale_return.cash_needs_session'))
        ->and(SaleReturn::count())->toBe(0);

    $session = openCashRegisterSession($this->admin, 100_000);
    $return = returnSale(SaleReturnType::Return, $data);

    expect($session->fresh()->expectedCash())->toBe(50_000)
        ->and($return->refund_payment_method_id)->toBe($this->cash->id)
        ->and(Payment::where('amount', -50_000)->sole()->cash_register_session_id)->toBe($session->id);
});

it('refuses refunding to the store-credit method', function () {
    expect(fn () => returnSale(SaleReturnType::ValueAdjustment, ['reason' => 'x', 'amount' => 10_000,
        'refund_amount' => 10_000, 'refund_payment_method_id' => $this->credit->id, 'store_credit_amount' => 0]))
        ->toThrow(ValidationException::class);
});

it('refuses returning more units than remain on a line', function () {
    expect(fn () => returnSale(SaleReturnType::Return, ['reason' => 'x', 'items' => [
        ['sale_item_id' => $this->line->id, 'quantity' => 2, 'restock' => false],
        ['sale_item_id' => $this->line->id, 'quantity' => 1, 'restock' => false],
    ], 'refund_amount' => 0, 'store_credit_amount' => 0]))->toThrow(ValidationException::class);

    returnSale(SaleReturnType::Return, ['reason' => 'x', 'items' => [['sale_item_id' => $this->line->id, 'quantity' => 2, 'restock' => false]],
        'refund_amount' => 0, 'store_credit_amount' => 0]);

    expect(fn () => returnSale(SaleReturnType::Return, ['reason' => 'x', 'items' => [['sale_item_id' => $this->line->id, 'quantity' => 1, 'restock' => false]],
        'refund_amount' => 0, 'store_credit_amount' => 0]))->toThrow(ValidationException::class, __('app.sale_return.too_many_units', ['count' => 0]))
        ->and(SaleReturn::count())->toBe(1);
});

it('does not let money leave beyond what was paid over the new value', function () {
    Payment::query()->delete();
    Payment::factory()->create(['sale_id' => $this->sale->id, 'payment_method_id' => $this->cash->id, 'amount' => 40_000]);

    expect(fn () => returnSale(SaleReturnType::ValueAdjustment, ['reason' => 'Descuento tardío', 'amount' => 30_000,
        'refund_amount' => 0, 'store_credit_amount' => 10_000]))->toThrow(ValidationException::class, __('app.sale_return.money_exceeds', ['max' => '0']));

    returnSale(SaleReturnType::ValueAdjustment, ['reason' => 'Descuento tardío', 'amount' => 30_000, 'refund_amount' => 0, 'store_credit_amount' => 0]);
    expect($this->sale->fresh()->balance)->toBe(30_000);
});

it('refuses an adjustment above the sale value', function () {
    expect(fn () => returnSale(SaleReturnType::ValueAdjustment, ['reason' => 'x', 'amount' => 100_001,
        'refund_amount' => 0, 'store_credit_amount' => 0]))->toThrow(ValidationException::class, __('app.sale_return.amount_exceeds'));
});

it('voids an undelivered sale keeping its number, refunding everything and restoring stock', function () {
    $number = $this->sale->number;
    $stockBefore = $this->product->fresh()->stock;

    $return = returnSale(SaleReturnType::Void, ['reason' => 'Error de digitación', 'refund_amount' => 0, 'store_credit_amount' => 100_000]);

    $sale = $this->sale->fresh();
    expect($sale->status)->toBe(SaleStatus::Voided)
        ->and($sale->number)->toBe($number)
        ->and($return->total)->toBe(100_000)
        ->and($return->items()->count())->toBe(0)
        ->and($this->customer->creditBalance())->toBe(100_000)
        ->and($this->product->fresh()->stock)->toBe($stockBefore + 2);

    expect(fn () => returnSale(SaleReturnType::Void, ['reason' => 'otra vez', 'refund_amount' => 0, 'store_credit_amount' => 0]))
        ->toThrow(ValidationException::class, __('app.sale_return.cannot_void'));
});

it('refuses a void that does not give back exactly what was paid', function () {
    expect(fn () => returnSale(SaleReturnType::Void, ['reason' => 'x', 'refund_amount' => 0, 'store_credit_amount' => 60_000]))
        ->toThrow(ValidationException::class, __('app.sale_return.void_money_mismatch', ['amount' => '100.000']))
        ->and($this->sale->fresh()->status)->not->toBe(SaleStatus::Voided);
});

it('does not restock twice when a sale with a restocked return is voided', function () {
    returnSale(SaleReturnType::Return, ['reason' => 'x', 'items' => [['sale_item_id' => $this->line->id, 'quantity' => 1, 'restock' => true]],
        'refund_amount' => 0, 'store_credit_amount' => 50_000]);
    expect($this->product->fresh()->stock)->toBe(4);

    returnSale(SaleReturnType::Void, ['reason' => 'x', 'refund_amount' => 0, 'store_credit_amount' => 50_000]);

    expect($this->product->fresh()->stock)->toBe(5)
        ->and($this->customer->creditBalance())->toBe(100_000);
});

it('refuses to void a delivered sale', function () {
    $this->sale->forceFill(['is_delivered' => true])->saveQuietly();

    expect(fn () => returnSale(SaleReturnType::Void, ['reason' => 'x', 'refund_amount' => 0, 'store_credit_amount' => 100_000]))
        ->toThrow(ValidationException::class, __('app.sale_return.cannot_void'));
});

it('cancels a plan separe keeping the configured fee', function () {
    $this->admin->company->update(['layaway_cancellation_fee_percent' => 10]);
    $this->sale->forceFill(['document_type' => 'layaway'])->saveQuietly();

    $return = returnSale(SaleReturnType::Void, ['reason' => 'Desiste', 'refund_amount' => 0, 'store_credit_amount' => 90_000]);

    expect($return->retained_amount)->toBe(10_000)
        ->and($this->customer->creditBalance())->toBe(90_000)
        ->and($this->sale->fresh()->status)->toBe(SaleStatus::Voided);
});

it('cancels unsent lens orders and records sent lenses as a loss', function () {
    $lens = SaleItem::factory()->create(['sale_id' => $this->sale->id, 'quantity' => 1, 'unit_price' => 0, 'unit_cost' => 80_000, 'tax_rate' => 0]);
    SaleItemLensConfig::factory()->create(['sale_item_id' => $lens->id]);
    $order = LensOrder::factory()->create(['sale_item_id' => $lens->id, 'lab_status' => LensOrderStatus::PendingAssignment]);
    $sentLens = SaleItem::factory()->create(['sale_id' => $this->sale->id, 'quantity' => 1, 'unit_price' => 0, 'unit_cost' => 70_000, 'tax_rate' => 0]);
    SaleItemLensConfig::factory()->create(['sale_item_id' => $sentLens->id]);
    LensOrder::factory()->create(['sale_item_id' => $sentLens->id, 'lab_status' => LensOrderStatus::Sent]);

    $return = returnSale(SaleReturnType::Void, ['reason' => 'x', 'refund_amount' => 0, 'store_credit_amount' => 100_000]);

    expect($order->fresh()->lab_status)->toBe(LensOrderStatus::Cancelled)
        ->and($return->loss_amount)->toBe(70_000);
});

it('never restocks a returned lens line', function () {
    $lens = SaleItem::factory()->create(['sale_id' => $this->sale->id, 'product_id' => $this->product->id, 'quantity' => 1, 'unit_price' => 0, 'unit_cost' => 80_000, 'tax_rate' => 0]);
    SaleItemLensConfig::factory()->create(['sale_item_id' => $lens->id]);
    LensOrder::factory()->create(['sale_item_id' => $lens->id, 'lab_status' => LensOrderStatus::Ready]);
    $stock = $this->product->fresh()->stock;

    $return = returnSale(SaleReturnType::Return, ['reason' => 'x', 'items' => [['sale_item_id' => $lens->id, 'quantity' => 1, 'restock' => true]],
        'refund_amount' => 0, 'store_credit_amount' => 0]);

    expect($this->product->fresh()->stock)->toBe($stock)
        ->and($return->loss_amount)->toBe(80_000)
        ->and($return->items()->sole()->restock)->toBeFalse();
});

/** @return array<string, list<string>> The validation errors of a return, or [] when it goes through. */
function saleReturnErrors(SaleReturnType $type, array $data): array
{
    try {
        returnSale($type, $data);
    } catch (ValidationException $exception) {
        return $exception->errors();
    }

    return [];
}

it('never values a return above what is left of the sale after an adjustment', function () {
    returnSale(SaleReturnType::ValueAdjustment, ['reason' => 'x', 'amount' => 100_000, 'refund_amount' => 0, 'store_credit_amount' => 100_000]);
    $items = [['sale_item_id' => $this->line->id, 'quantity' => 2, 'restock' => true]];

    expect(saleReturnErrors(SaleReturnType::Return, ['reason' => 'x', 'items' => $items, 'refund_amount' => 0, 'store_credit_amount' => 1]))
        ->toHaveKey('store_credit_amount');

    $return = returnSale(SaleReturnType::Return, ['reason' => 'x', 'items' => $items, 'refund_amount' => 0, 'store_credit_amount' => 0]);

    expect($return->total)->toBe(0)
        ->and($return->items()->sum('amount'))->toBe(0)
        ->and($this->sale->fresh()->netTotal())->toBe(0)
        ->and($this->customer->creditBalance())->toBe(100_000)
        ->and($this->product->fresh()->stock)->toBe(5);
});

it('returns a line unit by unit without rounding past its value', function () {
    $this->sale = Sale::factory()->create(['customer_id' => $this->customer->id, 'discount_percent' => 50, 'surcharge_percent' => 0]);
    $line = SaleItem::factory()->create(['sale_id' => $this->sale->id, 'quantity' => 2, 'unit_price' => 50_001, 'tax_rate' => 0]);
    expect($this->sale->fresh()->netTotal())->toBe(50_001);

    foreach ([1, 2] as $ignored) {
        returnSale(SaleReturnType::Return, ['reason' => 'x', 'items' => [['sale_item_id' => $line->id, 'quantity' => 1, 'restock' => false]],
            'refund_amount' => 0, 'store_credit_amount' => 0]);
    }

    expect((int) SaleReturn::where('sale_id', $this->sale->id)->sum('total'))->toBe(50_001)
        ->and($this->sale->fresh()->netTotal())->toBe(0);
});

it('refuses returns and adjustments on a cancelled plan separe, so the fee is never given back', function () {
    $this->admin->company->update(['layaway_cancellation_fee_percent' => 10]);
    $this->sale->forceFill(['document_type' => 'layaway'])->saveQuietly();
    $lens = SaleItem::factory()->create(['sale_id' => $this->sale->id, 'quantity' => 1, 'unit_price' => 0, 'unit_cost' => 70_000, 'tax_rate' => 0]);
    SaleItemLensConfig::factory()->create(['sale_item_id' => $lens->id]);
    LensOrder::factory()->create(['sale_item_id' => $lens->id, 'lab_status' => LensOrderStatus::Sent]);
    returnSale(SaleReturnType::Void, ['reason' => 'Desiste', 'refund_amount' => 0, 'store_credit_amount' => 90_000]);

    expect(saleReturnErrors(SaleReturnType::Return, ['reason' => 'x', 'items' => [['sale_item_id' => $lens->id, 'quantity' => 1, 'restock' => false]],
        'refund_amount' => 0, 'store_credit_amount' => 10_000]))->toBe(['reason' => [__('app.sale_return.sale_voided')]])
        ->and(saleReturnErrors(SaleReturnType::ValueAdjustment, ['reason' => 'x', 'amount' => 10_000, 'refund_amount' => 0, 'store_credit_amount' => 0]))
        ->toHaveKey('reason')
        ->and($this->customer->creditBalance())->toBe(90_000)
        ->and((int) SaleReturn::sum('loss_amount'))->toBe(70_000);
});

it('reports a missing store-credit method as a validation error', function () {
    PaymentMethod::withoutGlobalScopes()->whereKey($this->credit->id)->delete();

    expect(saleReturnErrors(SaleReturnType::ValueAdjustment, ['reason' => 'x', 'amount' => 10_000, 'refund_amount' => 0, 'store_credit_amount' => 10_000]))
        ->toBe(['store_credit_amount' => [__('app.sale_return.no_store_credit_method')]]);
});

it('refuses store credit on a sale without customer', function () {
    $this->sale->forceFill(['customer_id' => null])->saveQuietly();

    expect(saleReturnErrors(SaleReturnType::ValueAdjustment, ['reason' => 'x', 'amount' => 10_000, 'refund_amount' => 0, 'store_credit_amount' => 10_000]))
        ->toBe(['store_credit_amount' => [__('app.store_credit.needs_customer')]]);
});

it('only refunds through an active method of the sale company', function () {
    $inactive = PaymentMethod::factory()->create(['is_active' => false]);
    $foreign = PaymentMethod::factory()->create(['company_id' => Company::factory()->create()->id]);

    foreach ([$inactive, $foreign] as $method) {
        expect(saleReturnErrors(SaleReturnType::ValueAdjustment, ['reason' => 'x', 'amount' => 10_000,
            'refund_amount' => 10_000, 'refund_payment_method_id' => $method->id, 'store_credit_amount' => 0]))
            ->toHaveKey('refund_payment_method_id');
    }
});

it('rejects a line of another sale', function () {
    $other = SaleItem::factory()->create(['quantity' => 1, 'unit_price' => 10_000, 'tax_rate' => 0]);

    expect(saleReturnErrors(SaleReturnType::Return, ['reason' => 'x', 'items' => [['sale_item_id' => $other->id, 'quantity' => 1, 'restock' => false]],
        'refund_amount' => 0, 'store_credit_amount' => 0]))->toHaveKey('items.0.sale_item_id');
});

it('values a returned line with the discount actually charged, not the editable discount percent', function () {
    $this->sale->payments()->forceDelete();
    $this->sale->update(['discount_percent' => 50]);
    $this->sale->recalculateTotals();
    Payment::factory()->create(['sale_id' => $this->sale->id, 'payment_method_id' => $this->cash->id, 'amount' => 50_000]);
    // The percent is edited on the sale page without recalculating: the stored discount stays 50 000.
    $this->sale->update(['discount_percent' => 0]);

    $return = returnSale(SaleReturnType::Return, [
        'reason' => 'x', 'items' => [['sale_item_id' => $this->line->id, 'quantity' => 1, 'restock' => false]],
        'refund_amount' => 0, 'store_credit_amount' => 0,
    ]);

    expect($return->total)->toBe(25_000);
});
