<?php

namespace App\Actions;

use App\Enums\FrameSource;
use App\Enums\LensOrderStatus;
use App\Models\PaymentMethod;
use App\Models\Prescription;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RegisterSale
{
    private User $seller;

    /**
     * Create a sale with its line items, apply the company's combo slots and record
     * an optional initial payment.
     *
     * @param  array<string,mixed>  $data
     */
    public function handle(array $data, User $seller): Sale
    {
        $this->seller = $seller;

        return DB::transaction(function () use ($data, $seller): Sale {
            $sale = Sale::create([
                'customer_id' => $data['customer_id'] ?? null,
                'seller_id' => $seller->id,
                'created_by' => $seller->id,
                'document_type' => $data['document_type'] ?? 'order',
                'discount_percent' => $data['discount_percent'] ?? 0,
                'surcharge_percent' => $this->resolveSurcharge($data),
                'sold_at' => $data['sold_at'] ?? now()->toDateString(),
                'notes' => $data['notes'] ?? null,
            ]);

            $this->buildArmados($sale, $data['armados'] ?? []);
            foreach ($data['products'] ?? [] as $productLine) {
                $product = Product::find($productLine['product_id'] ?? null);
                $sale->items()->create([
                    'product_id' => $productLine['product_id'] ?? null,
                    'description' => $productLine['description'],
                    'quantity' => $productLine['quantity'] ?? 1,
                    'unit_price' => $productLine['unit_price'],
                    'unit_cost' => $productLine['unit_cost'] ?? 0,
                    ...SaleItem::taxSnapshotFor($product, $this->seller->company),
                ]);
            }
            $kitRules = app(ApplyKitRules::class);
            $kitRules->forStandaloneLines($sale, $seller);
            $kitRules->forSale($sale, $seller);

            $sale->recalculateTotals();

            $paid = (int) collect($data['payments'] ?? [])->sum(fn (array $p): int => max(0, (int) ($p['amount'] ?? 0)));
            if ($paid > $sale->total) {
                throw ValidationException::withMessages(['payments' => __('app.validation.payments_exceed_total')]);
            }

            foreach ($data['payments'] ?? [] as $payment) {
                if (($payment['amount'] ?? 0) <= 0) {
                    continue;
                }
                $sale->payments()->create([
                    'payment_method_id' => $payment['payment_method_id'],
                    'amount' => $payment['amount'],
                    'paid_at' => now()->toDateString(),
                    'received_by' => $seller->id,
                    'reference' => $payment['reference'] ?? null,
                ]);
            }

            return $sale->refresh();
        });
    }

    /**
     * The blended surcharge across every payment method used, weighted by amount —
     * Sale keeps a single surcharge_percent column, so a split payment is folded
     * into one weighted-average rate instead of one line per method.
     *
     * @param  array<string,mixed>  $data
     */
    private function resolveSurcharge(array $data): float
    {
        if (isset($data['surcharge_percent']) && empty($data['payments'])) {
            return (float) $data['surcharge_percent'];
        }

        $payments = collect($data['payments'] ?? [])->filter(fn (array $p): bool => ($p['amount'] ?? 0) > 0);
        $totalAmount = (int) $payments->sum('amount');

        if ($totalAmount === 0) {
            return 0.0;
        }

        // Store credit is the customer's own money: it never carries a surcharge.
        $surchargeByMethod = PaymentMethod::whereIn('id', $payments->pluck('payment_method_id')->unique())
            ->where('is_store_credit', false)
            ->pluck('surcharge_percent', 'id');

        $weighted = $payments->sum(
            fn (array $p): float => (float) ($surchargeByMethod[$p['payment_method_id']] ?? 0) * $p['amount']
        );

        return $weighted / $totalAmount;
    }

    /**
     * Build each armado as a grouped set of lines (lens + optional frame + combo extras).
     *
     * @param  array<int,array<string,mixed>>  $armados
     */
    private function buildArmados(Sale $sale, array $armados): void
    {
        foreach ($armados as $index => $armado) {
            $groupKey = 'g'.($index + 1);
            $lens = $armado['lens'];

            $prescription = isset($armado['prescription_id']) ? Prescription::find($armado['prescription_id']) : null;

            $resolved = (new ResolveLensPricing)->handle(
                (int) $lens['lens_type_id'],
                (int) $lens['lens_technology_id'],
                (int) $lens['lens_material_id'],
                $lens['treatment_ids'] ?? [],
                $prescription,
                isset($lens['supplier_id']) ? (int) $lens['supplier_id'] : null,
            );

            $unitPrice = $resolved['price'];

            // A seller-entered override always wins over the computed price above —
            // it's a distinct field from unit_price precisely so a client can never
            // silently swap out the protected computed price.
            if (isset($lens['price_override'])) {
                $unitPrice = (int) $lens['price_override'];
            }

            $combination = $resolved['combination'];

            $lensItem = $sale->items()->create([
                'group_key' => $groupKey,
                'product_id' => null,
                'description' => $lens['description'],
                'quantity' => $lens['quantity'] ?? 1,
                'unit_price' => $unitPrice,
                'unit_cost' => $resolved['cost'],
                ...SaleItem::taxSnapshot($combination->tax ?? ProductCategory::keyed('lens')?->defaultTax, $this->seller->company),
            ]);

            $lensConfig = $lensItem->lensConfig()->create([
                // The sale's customer pays; the armado may be for someone else.
                'patient_id' => $armado['patient_id'] ?? $sale->customer_id,
                'prescription_id' => $armado['prescription_id'] ?? null,
                'lens_combination_id' => $combination->id,
                'type_name' => $combination->lensType->name,
                'technology_name' => $combination->lensTechnology->name,
                'material_name' => $combination->lensMaterial->name,
                'combination_cost' => $resolved['price_row']->cost,
                'combination_price' => $resolved['price_row']->price,
                'installation_price' => $combination->installation_price,
            ]);

            foreach ($resolved['treatments'] as $treatment) {
                $lensConfig->treatments()->create([
                    'lens_treatment_id' => $treatment->id,
                    'name' => $treatment->name,
                    'price' => $treatment->price,
                    'cost' => $treatment->cost,
                ]);
            }

            // Every lens in this business is made-to-order and needs a lab order.
            $ownFrame = ! empty($armado['own_frame']);
            $measurements = $armado['measurements'] ?? [];

            $lensItem->lensOrder()->create([
                // The lab that priced the lens makes it; the order carries everything it needs.
                'supplier_id' => $resolved['price_row']->supplier_id,
                'lab_status' => LensOrderStatus::PendingAssignment,
                'prescription_snapshot' => $prescription?->labSnapshot(),
                'od_pd' => $prescription?->od_pd,
                'os_pd' => $prescription?->os_pd,
                'od_height' => $measurements['od_height'] ?? null,
                'os_height' => $measurements['os_height'] ?? null,
                'frame_a' => $measurements['frame_a'] ?? null,
                'frame_b' => $measurements['frame_b'] ?? null,
                'frame_dbl' => $measurements['frame_dbl'] ?? null,
                'frame_type' => $measurements['frame_type'] ?? null,
                'frame_source' => $ownFrame ? FrameSource::CustomerOwn : FrameSource::Sold,
                'customer_frame_description' => $ownFrame ? ($armado['own_frame_description'] ?? null) : null,
                'customer_frame_condition' => $ownFrame ? ($armado['own_frame_condition'] ?? null) : null,
            ]);

            if (empty($armado['own_frame']) && ! empty($armado['frame'])) {
                $frame = $armado['frame'];
                $sale->items()->create([
                    'group_key' => $groupKey,
                    'product_id' => $frame['product_id'] ?? null,
                    'description' => $frame['description'],
                    'quantity' => $frame['quantity'] ?? 1,
                    'unit_price' => $this->seller->company->armadoFrameUnitPrice((int) $frame['unit_price']),
                    'unit_cost' => $frame['unit_cost'] ?? 0,
                    ...SaleItem::taxSnapshotFor(Product::find($frame['product_id'] ?? null), $this->seller->company),
                ]);
            }

            app(ApplyKitRules::class)->forArmado($sale, $groupKey, $armado['slots'] ?? [], $this->seller);
        }
    }
}
