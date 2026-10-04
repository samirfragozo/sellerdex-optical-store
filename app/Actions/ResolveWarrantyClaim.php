<?php

namespace App\Actions;

use App\Enums\RemakeReason;
use App\Enums\RemakeResponsible;
use App\Enums\SaleReturnType;
use App\Enums\StockMovementType;
use App\Enums\WarrantyClaimStatus;
use App\Enums\WarrantyClaimType;
use App\Enums\WarrantyResolution;
use App\Enums\WarrantyResponsible;
use App\Models\Product;
use App\Models\SaleItem;
use App\Models\User;
use App\Models\WarrantyClaim;
use App\Support\StockLedger;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Close a claim the Ley 1480 way: repair, replace (a lens is remade at the lab), refund with no
 * deductions, or reject with a reason. Resolving needs an admin, directly or through their PIN.
 */
class ResolveWarrantyClaim
{
    /**
     * What a warranty refund must pay back (Ley 1480: no deductions): the value of the line's returnable units,
     * valued as RegisterSaleReturn values a returned line, capped at what the customer has actually paid.
     */
    public static function refundDue(SaleItem $item): int
    {
        $sale = $item->sale;
        $factor = $sale->chargedFactor();
        $valueOf = fn (int $units): int => (int) round($item->line_total * $units / $item->quantity * $factor);
        $net = max(0, $sale->netTotal());
        $lineValue = min($net, $valueOf($item->quantity) - $valueOf($item->returnedQuantity()));

        return max(0, $sale->totalPaid() - ($net - $lineValue));
    }

    /** @param  array{responsible?: string|null, store_cost?: int, rejection_reason?: string|null, replacement_product_id?: int|null, refund_amount?: int, refund_payment_method_id?: int|null, store_credit_amount?: int}  $data */
    public function handle(WarrantyClaim $claim, WarrantyResolution $resolution, array $data, User $actor, User $approver): WarrantyClaim
    {
        if (! $approver->isAdmin()) {
            throw ValidationException::withMessages(['approval_pin' => __('app.approval.required')]);
        }

        if (! in_array($claim->status, [WarrantyClaimStatus::InReview, WarrantyClaimStatus::AtSupplier], true)) {
            throw new DomainException(__('app.warranty.invalid_transition'));
        }

        if ($resolution === WarrantyResolution::Rejected && blank($data['rejection_reason'] ?? null)) {
            throw ValidationException::withMessages(['rejection_reason' => __('app.warranty.rejection_reason_required')]);
        }

        $item = $claim->saleItem()->with(['sale', 'product', 'lensOrder'])->firstOrFail();
        $responsible = filled($data['responsible'] ?? null) ? WarrantyResponsible::from($data['responsible']) : null;
        $storeCost = $responsible === WarrantyResponsible::Store ? (int) ($data['store_cost'] ?? 0) : 0;

        return DB::transaction(function () use ($claim, $resolution, $data, $actor, $approver, $item, $responsible, $storeCost): WarrantyClaim {
            // The locked row is the authoritative status: a concurrent resolve of the same claim stops here.
            $claim = WarrantyClaim::withoutGlobalScopes()->whereKey($claim->id)->lockForUpdate()->firstOrFail();
            if (! in_array($claim->status, [WarrantyClaimStatus::InReview, WarrantyClaimStatus::AtSupplier], true)) {
                throw new DomainException(__('app.warranty.invalid_transition'));
            }

            $changes = [];
            $replaces = in_array($resolution, [WarrantyResolution::SameReplacement, WarrantyResolution::OtherReplacement], true);

            if ($replaces && $item->lensOrder !== null) {
                $remake = $item->lensOrder->remake(
                    $claim->type === WarrantyClaimType::Adaptation ? RemakeReason::NonAdaptation : RemakeReason::LabDefect,
                    $responsible === WarrantyResponsible::Supplier ? RemakeResponsible::Lab : RemakeResponsible::Store,
                    $storeCost,
                    notes: $claim->customer_description,
                );
                $changes['lens_order_id'] = $remake->id;
            } elseif ($resolution === WarrantyResolution::SameReplacement && $item->product !== null) {
                StockLedger::record($item->product, StockMovementType::WarrantyReplacement, -1, $claim);
            } elseif ($resolution === WarrantyResolution::OtherReplacement) {
                $product = Product::find($data['replacement_product_id'] ?? null)
                    ?? throw ValidationException::withMessages(['replacement_product_id' => __('app.warranty.replacement_product_required')]);
                StockLedger::record($product, StockMovementType::WarrantyReplacement, -1, $claim);
                $changes['replacement_product_id'] = $product->id;
            } elseif ($resolution === WarrantyResolution::Refund) {
                $due = self::refundDue($item);
                if ((int) ($data['refund_amount'] ?? 0) + (int) ($data['store_credit_amount'] ?? 0) !== $due) {
                    throw ValidationException::withMessages(['refund_amount' => __('app.warranty.refund_must_be_full', ['amount' => '$'.number_format($due, 0, ',', '.')])]);
                }

                $return = app(RegisterSaleReturn::class)->handle($item->sale, SaleReturnType::Return, [
                    'reason' => __('app.warranty.refund_reason', ['id' => $claim->id]),
                    'items' => [['sale_item_id' => $item->id, 'quantity' => $item->returnableQuantity(), 'restock' => false]],
                    'refund_amount' => (int) ($data['refund_amount'] ?? 0),
                    'refund_payment_method_id' => $data['refund_payment_method_id'] ?? null,
                    'store_credit_amount' => (int) ($data['store_credit_amount'] ?? 0),
                ], $actor, $approver);
                $changes['sale_return_id'] = $return->id;
            }

            $claim->update([
                ...$changes,
                'status' => WarrantyClaimStatus::Resolved,
                'resolution' => $resolution,
                'rejection_reason' => $resolution === WarrantyResolution::Rejected ? $data['rejection_reason'] : null,
                'responsible' => $responsible,
                'store_cost' => $storeCost,
                'resolved_by' => $approver->id,
                'resolved_at' => now(),
            ]);

            return $claim;
        });
    }
}
