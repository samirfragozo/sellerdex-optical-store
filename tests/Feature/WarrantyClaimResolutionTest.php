<?php

use App\Actions\ResolveWarrantyClaim;
use App\Enums\RemakeReason;
use App\Enums\RemakeResponsible;
use App\Enums\SaleReturnType;
use App\Enums\StockMovementType;
use App\Enums\WarrantyClaimStatus;
use App\Enums\WarrantyClaimType;
use App\Enums\WarrantyResolution;
use App\Models\LensOrder;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\SaleItemLensConfig;
use App\Models\StockMovement;
use App\Models\User;
use App\Models\WarrantyClaim;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    $this->admin = User::factory()->admin()->create();
    $this->actingAs($this->admin);
    $this->admin->company->update(['tracks_inventory' => true]);
    $this->product = Product::factory()->create(['is_stockable' => true, 'stock' => 5]);
    $this->sale = Sale::factory()->create(['is_delivered' => true, 'delivered_at' => today()->subDays(5), 'discount_percent' => 0, 'surcharge_percent' => 0]);
    $this->item = SaleItem::factory()->create(['sale_id' => $this->sale->id, 'product_id' => $this->product->id, 'quantity' => 1, 'unit_price' => 100_000, 'tax_rate' => 0]);
    // Selling the line already took its unit out of stock.
    $this->baseline = StockMovement::count();
    $this->claim = WarrantyClaim::factory()->create(['sale_item_id' => $this->item->id, 'status' => WarrantyClaimStatus::InReview]);
});

function resolveClaim(WarrantyClaim $claim, WarrantyResolution $resolution, array $data = [], ?User $approver = null): WarrantyClaim
{
    return app(ResolveWarrantyClaim::class)->handle($claim, $resolution, $data, test()->admin, $approver ?? test()->admin);
}

/** @return list<string> */
function warrantyErrorKeys(Closure $callback): array
{
    try {
        $callback();
    } catch (ValidationException $exception) {
        return array_keys($exception->errors());
    }

    return [];
}

it('resolves a repair with no side effects', function () {
    $claim = resolveClaim($this->claim, WarrantyResolution::Repair);

    expect($claim)->status->toBe(WarrantyClaimStatus::Resolved)
        ->resolution->toBe(WarrantyResolution::Repair)
        ->resolved_by->toBe($this->admin->id)
        ->resolved_at->not->toBeNull()
        ->and(StockMovement::count())->toBe($this->baseline)
        ->and($this->product->fresh()->stock)->toBe(4);
});

it('needs a reason to reject a claim', function () {
    expect(warrantyErrorKeys(fn () => resolveClaim($this->claim, WarrantyResolution::Rejected)))->toBe(['rejection_reason']);

    $claim = resolveClaim($this->claim->fresh(), WarrantyResolution::Rejected, ['rejection_reason' => 'Golpe del cliente']);

    expect($claim->rejection_reason)->toBe('Golpe del cliente')->and($claim->status)->toBe(WarrantyClaimStatus::Resolved);
});

it('takes the same product out of stock on a same replacement', function () {
    resolveClaim($this->claim, WarrantyResolution::SameReplacement, ['responsible' => 'supplier']);

    $movement = StockMovement::latest('id')->first();
    expect(StockMovement::count())->toBe($this->baseline + 1)
        ->and($movement->type)->toBe(StockMovementType::WarrantyReplacement)
        ->and($movement->quantity)->toBe(-1)
        ->and($this->product->fresh()->stock)->toBe(3);
});

it('takes the replacement product out of stock on another replacement', function () {
    $other = Product::factory()->create(['is_stockable' => true, 'stock' => 3]);

    expect(warrantyErrorKeys(fn () => resolveClaim($this->claim, WarrantyResolution::OtherReplacement)))->toBe(['replacement_product_id']);

    $claim = resolveClaim($this->claim->fresh(), WarrantyResolution::OtherReplacement, ['replacement_product_id' => $other->id]);

    expect($claim->replacement_product_id)->toBe($other->id)
        ->and($other->fresh()->stock)->toBe(2)
        ->and($this->product->fresh()->stock)->toBe(4);
});

it('remakes a lens at the lab on a replacement', function (WarrantyClaimType $type, RemakeReason $reason) {
    $lens = SaleItem::factory()->create(['sale_id' => $this->sale->id, 'product_id' => null, 'quantity' => 1]);
    SaleItemLensConfig::factory()->create(['sale_item_id' => $lens->id]);
    $order = LensOrder::factory()->ready()->create(['sale_item_id' => $lens->id]);
    $claim = WarrantyClaim::factory()->create(['sale_item_id' => $lens->id, 'type' => $type, 'status' => WarrantyClaimStatus::InReview]);

    $claim = resolveClaim($claim, WarrantyResolution::SameReplacement, ['responsible' => 'store', 'store_cost' => 40_000]);
    $remake = LensOrder::findOrFail($claim->lens_order_id);

    expect($remake->remake_of_id)->toBe($order->id)
        ->and($remake->remake_responsible)->toBe(RemakeResponsible::Store)
        ->and($remake->remake_cost)->toBe(40_000)
        ->and($remake->remake_reason)->toBe($reason);
})->with([
    'warranty' => [WarrantyClaimType::Warranty, RemakeReason::LabDefect],
    'adaptation' => [WarrantyClaimType::Adaptation, RemakeReason::NonAdaptation],
]);

it('refunds the full amount with no fee, even on a layaway', function () {
    $this->admin->company->update(['layaway_cancellation_fee_percent' => 10]);
    $this->sale->forceFill(['document_type' => 'layaway'])->saveQuietly();
    $cash = PaymentMethod::where('is_default', true)->first() ?? PaymentMethod::factory()->create(['is_default' => true, 'name' => 'Efectivo']);
    Payment::factory()->create(['sale_id' => $this->sale->id, 'payment_method_id' => $cash->id, 'amount' => 100_000]);
    openCashRegisterSession($this->admin, 100_000);

    $claim = resolveClaim($this->claim, WarrantyResolution::Refund, ['refund_amount' => 100_000, 'refund_payment_method_id' => $cash->id]);
    $return = $claim->saleReturn;

    expect($return->type)->toBe(SaleReturnType::Return)
        ->and($return->refund_amount)->toBe(100_000)
        ->and($return->retained_amount)->toBe(0)
        ->and($return->items()->sole()->restock)->toBeFalse()
        ->and($this->product->fresh()->stock)->toBe(4);
});

it('refuses a refund that is not the full amount paid', function (int $amount) {
    $cash = PaymentMethod::where('is_default', true)->first() ?? PaymentMethod::factory()->create(['is_default' => true, 'name' => 'Efectivo']);
    Payment::factory()->create(['sale_id' => $this->sale->id, 'payment_method_id' => $cash->id, 'amount' => 100_000]);
    openCashRegisterSession($this->admin, 100_000);

    expect(ResolveWarrantyClaim::refundDue($this->item->fresh()))->toBe(100_000)
        ->and(warrantyErrorKeys(fn () => resolveClaim($this->claim, WarrantyResolution::Refund, ['refund_amount' => $amount, 'refund_payment_method_id' => $cash->id])))->toBe(['refund_amount'])
        ->and($this->claim->fresh()->status)->toBe(WarrantyClaimStatus::InReview);
})->with([0, 60_000]);

it('refuses to resolve a claim that was not reviewed', function () {
    $this->claim->update(['status' => WarrantyClaimStatus::Received]);

    resolveClaim($this->claim, WarrantyResolution::Repair);
})->throws(DomainException::class);

it('refuses to resolve a claim already resolved by someone else', function () {
    $stale = $this->claim->fresh();
    resolveClaim($this->claim, WarrantyResolution::Repair);

    resolveClaim($stale, WarrantyResolution::Rejected, ['rejection_reason' => 'x']);
})->throws(DomainException::class);

it('refuses a non-admin approver', function () {
    $seller = User::factory()->seller()->create(['company_id' => $this->sale->company_id]);

    try {
        resolveClaim($this->claim, WarrantyResolution::Repair, [], $seller);
    } catch (ValidationException $exception) {
        expect($exception->errors())->toHaveKey('approval_pin');

        return;
    }

    $this->fail('Expected a ValidationException.');
});

it('stamps the delivery date', function () {
    $claim = resolveClaim($this->claim, WarrantyResolution::Repair);
    $claim->advance(WarrantyClaimStatus::Delivered);

    expect($claim->fresh())->status->toBe(WarrantyClaimStatus::Delivered)->and($claim->fresh()->delivered_at->toDateString())->toBe(today()->toDateString());
});

it('prints the receipt for the claim company only', function () {
    $this->get(route('documents.warranty-claim', $this->claim))
        ->assertOk()
        ->assertSee('#'.$this->claim->id)
        ->assertSee($this->claim->customer_description);

    // Another company's claim is hidden by the tenant scope, like the other documents.
    $this->actingAs(User::factory()->admin()->create());
    $this->get(route('documents.warranty-claim', $this->claim->id))->assertNotFound();
});
