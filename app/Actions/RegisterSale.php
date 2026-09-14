<?php

namespace App\Actions;

use App\Enums\LensOrderStatus;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\Sale;
use App\Models\User;
use App\Support\LensPricing;
use Illuminate\Support\Facades\DB;

class RegisterSale
{
    private const EXAM_SURCHARGE = 20000;

    private const BAG_THRESHOLD = 215000;

    private const SKU_EXAM = 'SRV-EXAMEN';

    private const SKU_PANO = 'ACC-PANO';

    private const SKU_LIQUIDO = 'ACC-LIQUIDO';

    private const SKU_FUNDA = 'ACC-FUNDA';

    /**
     * Create a sale with its line items, compose the combo (consumables, free exam, bag,
     * bundles, funda) and record an optional initial payment.
     *
     * @param  array<string,mixed>  $data
     */
    public function handle(array $data, User $seller): Sale
    {
        return DB::transaction(function () use ($data, $seller): Sale {
            $sale = Sale::create([
                'customer_id' => $data['customer_id'] ?? null,
                'seller_id' => $seller->id,
                'created_by' => $seller->id,
                'prescription_id' => $data['prescription_id'] ?? null,
                'document_type' => $data['document_type'] ?? 'order',
                'discount_percent' => $data['discount_percent'] ?? 0,
                'tip_percent' => $data['tip_percent'] ?? 0,
                'surcharge_percent' => $this->resolveSurcharge($data),
                'sold_at' => $data['sold_at'] ?? now()->toDateString(),
                'notes' => $data['notes'] ?? null,
            ]);

            if (array_key_exists('items', $data)) {
                foreach ($data['items'] as $item) {
                    $product = Product::find($item['product_id'] ?? null);
                    $sale->items()->create([
                        'product_id' => $item['product_id'] ?? null,
                        'description' => $item['description'],
                        'quantity' => $item['quantity'],
                        'unit_price' => $item['unit_price'],
                        'unit_cost' => $item['unit_cost'] ?? 0,
                        'tax_amount' => $this->taxFor($product, (int) $item['unit_price'], (int) $item['quantity']),
                    ]);
                }
                $this->composeCombo($sale, $data['combo'] ?? null);
                $this->applyAdditions($sale);
            } else {
                $this->buildArmados($sale, $data['armados'] ?? []);
                foreach ($data['products'] ?? [] as $productLine) {
                    $product = Product::find($productLine['product_id'] ?? null);
                    $sale->items()->create([
                        'product_id' => $productLine['product_id'] ?? null,
                        'description' => $productLine['description'],
                        'quantity' => $productLine['quantity'] ?? 1,
                        'unit_price' => $productLine['unit_price'],
                        'unit_cost' => $productLine['unit_cost'] ?? 0,
                        'tax_amount' => $this->taxFor($product, (int) $productLine['unit_price'], (int) ($productLine['quantity'] ?? 1)),
                    ]);
                }
                $this->applyAdditions($sale);
                if (empty($data['armados'])) {
                    $sale->load('items.product.category');
                    $this->applyFunda($sale);
                }
                $this->applyBag($sale);
            }

            $sale->recalculateTotals();

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

        $surchargeByMethod = PaymentMethod::whereIn('id', $payments->pluck('payment_method_id')->unique())
            ->pluck('surcharge_percent', 'id');

        $weighted = $payments->sum(
            fn (array $p): float => (float) ($surchargeByMethod[$p['payment_method_id']] ?? 0) * $p['amount']
        );

        return $weighted / $totalAmount;
    }

    /**
     * Compose a lens combo (or add a funda for a standalone frame).
     *
     * @param  array<string,mixed>|null  $combo
     */
    private function composeCombo(Sale $sale, ?array $combo): void
    {
        $sale->load('items.product.category');
        $lensLine = $sale->items->first(fn ($i) => $i->product?->category?->key === 'lens');

        if ($lensLine === null) {
            $this->applyFunda($sale);

            return;
        }

        $combo ??= ['estuche' => 'small', 'include_liquid' => false, 'include_pano' => true, 'with_exam' => false];

        // Free exam: +20k once on the lens, $0 exam line.
        if (! empty($combo['with_exam'])) {
            $lensLine->update(['unit_price' => $lensLine->unit_price + self::EXAM_SURCHARGE]);
            $this->addZeroLine($sale, self::SKU_EXAM);
        }

        // Montura included at $0 inside a combo.
        foreach ($sale->items as $item) {
            if ($item->product?->category?->key === 'frame' && $item->unit_price !== 0) {
                $item->update(['unit_price' => 0]);
            }
        }

        // Consumables.
        $estucheSku = ($combo['estuche'] ?? 'small') === 'large' ? 'ACC-ESTUCHE-LARGE' : 'ACC-ESTUCHE-SMALL';
        $this->addZeroLine($sale, $estucheSku);
        if (! empty($combo['include_pano'])) {
            $this->addZeroLine($sale, self::SKU_PANO);
        }
        if (! empty($combo['include_liquid'])) {
            $this->addZeroLine($sale, self::SKU_LIQUIDO);
        }

        // Bag by merchandise total (subtotal - discount), before surcharge.
        $this->applyBag($sale);
    }

    /** Add each active `product_additions` bundle line for every sold product (e.g. contact-lens solution). */
    private function applyAdditions(Sale $sale): void
    {
        $sale->load('items.product.additions');
        foreach ($sale->items as $item) {
            $product = $item->product;
            if ($product === null) {
                continue;
            }
            foreach ($product->additions->where('pivot.is_active', true) as $addition) {
                $this->addAdditionLine(
                    $sale,
                    $addition,
                    $addition->price + $addition->pivot->price,
                    $addition->pivot->quantity,
                );
            }
        }
    }

    /** Add a resolved addition as its own sale line, skipping if already present. */
    private function addAdditionLine(Sale $sale, Product $addition, int $unitPrice, int $quantity): void
    {
        $exists = $sale->items()
            ->where('product_id', $addition->id)
            ->whereNull('group_key')
            ->exists();
        if ($exists) {
            return;
        }
        $sale->items()->create([
            'product_id' => $addition->id,
            'description' => $addition->name,
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'unit_cost' => $addition->cost,
            // Additions ride along with an armado (lens/frame), which is tax-exempt
            // in this business — see the lens/frame lines below for the same reasoning.
            'tax_amount' => 0,
        ]);
    }

    /** A standalone frame/sunglasses sale (no lens) gets a funda. */
    private function applyFunda(Sale $sale): void
    {
        $hasFrame = $sale->items->contains(fn ($i) => $i->product?->category?->key === 'frame');
        if ($hasFrame) {
            $this->addZeroLine($sale, self::SKU_FUNDA);
        }
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

            $unitPrice = (int) ($lens['unit_price'] ?? 0);
            $unitCost = (int) ($lens['unit_cost'] ?? 0);
            $resolvedOptions = null;
            $lensProduct = Product::find($lens['product_id'] ?? null);

            if ($lensProduct !== null && $lensProduct->optionGroups()->exists()) {
                $resolved = (new ResolveProductOptions)->handle($lensProduct, $lens['option_ids'] ?? []);
                $unitCost = $lensProduct->cost + $resolved['cost'];

                if ($lensProduct->category?->key === 'lens' && isset($lensProduct->specs['design'])) {
                    // A design-based lens (seeded by ProductCatalogSeeder) prices by
                    // formula, not by flat option sum: cost×markup floored by the
                    // chosen filter's tier (see LensPricing). Other lens-category
                    // option products (e.g. ad-hoc ones without a design) keep the
                    // generic flat-sum behavior in the else branch below.
                    $filterName = $resolved['options']->first(fn ($o) => str_starts_with($o->group->name, 'Filtro'))?->name;
                    $unitPrice = LensPricing::price($unitCost, $filterName);
                } else {
                    $unitPrice = $lensProduct->price + $resolved['price'];
                }

                $resolvedOptions = $resolved['options'];
            }

            // A seller-entered override always wins over the computed price above —
            // it's a distinct field from unit_price precisely so a client can never
            // silently swap out the protected computed price (see RegisterSaleOptionsTest).
            if (isset($lens['price_override'])) {
                $unitPrice = (int) $lens['price_override'];
            }

            $lensItem = $sale->items()->create([
                'group_key' => $groupKey,
                'product_id' => $lens['product_id'] ?? null,
                'description' => $lens['description'],
                'quantity' => $lens['quantity'] ?? 1,
                'unit_price' => $unitPrice,
                'unit_cost' => $unitCost,
                // Lenses are tax-exempt in this business (see Product::$tax_rate on the
                // lens product, which is never applied here — matches the client-side
                // cart preview, which never taxes armado lines either).
                'tax_amount' => 0,
            ]);

            if ($resolvedOptions !== null) {
                foreach ($resolvedOptions as $option) {
                    $lensItem->options()->create([
                        'option_group_id' => $option->option_group_id,
                        'option_id' => $option->id,
                        'option_group_name' => $option->group->name,
                        'option_name' => $option->name,
                        'price' => $option->price,
                        'cost' => $option->cost,
                    ]);
                }
            }

            if ($lensProduct?->category?->generates_lab_order) {
                $lensItem->lensOrder()->create([
                    'supplier_id' => null,
                    'lab_status' => LensOrderStatus::PendingAssignment,
                ]);
            }

            if (empty($armado['own_frame']) && ! empty($armado['frame'])) {
                $frame = $armado['frame'];
                $sale->items()->create([
                    'group_key' => $groupKey,
                    'product_id' => $frame['product_id'] ?? null,
                    'description' => $frame['description'],
                    'quantity' => $frame['quantity'] ?? 1,
                    'unit_price' => $frame['unit_price'],
                    'unit_cost' => $frame['unit_cost'] ?? 0,
                    // Frames are tax-exempt in this business, same as lenses above.
                    'tax_amount' => 0,
                ]);
            }

            $this->composeArmadoCombo($sale, $groupKey, $armado['combo'] ?? null);
        }
    }

    /**
     * Apply the combo rules within a single armado group.
     *
     * @param  array<string,mixed>|null  $combo
     */
    private function composeArmadoCombo(Sale $sale, string $groupKey, ?array $combo): void
    {
        $combo ??= ['estuche' => 'small', 'include_liquid' => false, 'include_pano' => true, 'with_exam' => false];
        $sale->load('items.product.category');

        $groupItems = $sale->items->where('group_key', $groupKey);
        $lensLine = $groupItems->first(fn ($i) => $i->product?->category?->key === 'lens');

        if ($lensLine === null) {
            return;
        }

        if (! empty($combo['with_exam'])) {
            $lensLine->update(['unit_price' => $lensLine->unit_price + self::EXAM_SURCHARGE]);
            $this->addZeroLine($sale, self::SKU_EXAM, $groupKey);
        }

        foreach ($groupItems as $item) {
            if ($item->product?->category?->key === 'frame' && $item->unit_price !== 0) {
                $item->update(['unit_price' => 0]);
            }
        }

        $estucheSku = ($combo['estuche'] ?? 'small') === 'large' ? 'ACC-ESTUCHE-LARGE' : 'ACC-ESTUCHE-SMALL';
        $this->addZeroLine($sale, $estucheSku, $groupKey);
        if (! empty($combo['include_pano'])) {
            $this->addZeroLine($sale, self::SKU_PANO, $groupKey);
        }
        if (! empty($combo['include_liquid'])) {
            $this->addZeroLine($sale, self::SKU_LIQUIDO, $groupKey);
        }
    }

    /** Add the bag once for the whole sale, chosen by merchandise total. */
    private function applyBag(Sale $sale): void
    {
        $sale->recalculateTotals();
        $merch = max(0, (int) $sale->subtotal - (int) $sale->discount);
        $bagSku = $merch >= self::BAG_THRESHOLD ? 'ACC-BOLSA-PAPEL' : 'ACC-BOLSA-PLASTICO';
        $this->addZeroLine($sale, $bagSku);
    }

    /** Idempotently add a $0 line for a consumable SKU within an optional armado group. */
    private function addZeroLine(Sale $sale, string $sku, ?string $groupKey = null): void
    {
        $product = Product::where('sku', $sku)->first();
        if ($product === null) {
            return;
        }
        $exists = $sale->items()
            ->where('product_id', $product->id)
            ->where('group_key', $groupKey)
            ->exists();
        if ($exists) {
            return;
        }
        $sale->items()->create([
            'group_key' => $groupKey,
            'product_id' => $product->id,
            'description' => $product->name,
            'quantity' => 1,
            'unit_price' => 0,
            'unit_cost' => $product->cost,
            'tax_amount' => 0,
        ]);
        $sale->load('items.product.category');
    }

    /** Per-line tax snapshot, based on the product's tax rate at sale time. */
    private function taxFor(?Product $product, int $unitPrice, int $quantity): int
    {
        if ($product === null || (float) $product->tax_rate <= 0) {
            return 0;
        }

        return (int) round($unitPrice * $quantity * ((float) $product->tax_rate) / 100);
    }
}
