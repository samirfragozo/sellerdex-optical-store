<?php

namespace App\Actions;

use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\Sale;
use App\Models\User;
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
                'customer_id' => $data['customer_id'],
                'seller_id' => $seller->id,
                'created_by' => $seller->id,
                'prescription_id' => $data['prescription_id'] ?? null,
                'document_type' => $data['document_type'] ?? 'order',
                'discount' => $data['discount'] ?? 0,
                'surcharge_percent' => $this->resolveSurcharge($data),
                'sold_at' => $data['sold_at'] ?? now()->toDateString(),
                'notes' => $data['notes'] ?? null,
            ]);

            foreach ($data['items'] as $item) {
                $sale->items()->create([
                    'product_id' => $item['product_id'] ?? null,
                    'description' => $item['description'],
                    'quantity' => $item['quantity'],
                    'unit_price' => $item['unit_price'],
                    'unit_cost' => $item['unit_cost'] ?? 0,
                ]);
            }

            $this->composeCombo($sale, $data['combo'] ?? null);
            $this->applyIncludes($sale);

            $sale->recalculateTotals();

            if (! empty($data['payment']) && ($data['payment']['amount'] ?? 0) > 0) {
                $sale->payments()->create([
                    'payment_method_id' => $data['payment']['payment_method_id'],
                    'amount' => $data['payment']['amount'],
                    'paid_at' => now()->toDateString(),
                    'received_by' => $seller->id,
                    'reference' => $data['payment']['reference'] ?? null,
                ]);
            }

            return $sale->refresh();
        });
    }

    /**
     * @param  array<string,mixed>  $data
     */
    private function resolveSurcharge(array $data): float
    {
        if (isset($data['surcharge_percent'])) {
            return (float) $data['surcharge_percent'];
        }
        if (! empty($data['payment']['payment_method_id'])) {
            return (float) (PaymentMethod::whereKey($data['payment']['payment_method_id'])->value('surcharge_percent') ?? 0);
        }

        return 0.0;
    }

    /**
     * Compose a lens combo (or add a funda for a standalone frame).
     *
     * @param  array<string,mixed>|null  $combo
     */
    private function composeCombo(Sale $sale, ?array $combo): void
    {
        $sale->load('items.product.category');
        $lensLine = $sale->items->first(fn ($i) => $i->product?->category?->name === 'Lente');

        if ($lensLine === null) {
            $this->applyFunda($sale);

            return;
        }

        $combo ??= ['forro' => 'small', 'include_liquid' => false, 'with_exam' => false];

        // Free exam: +20k once on the lens, $0 exam line.
        if (! empty($combo['with_exam'])) {
            $lensLine->update(['unit_price' => $lensLine->unit_price + self::EXAM_SURCHARGE]);
            $this->addZeroLine($sale, self::SKU_EXAM);
        }

        // Montura included at $0 inside a combo.
        foreach ($sale->items as $item) {
            if ($item->product?->category?->name === 'Montura' && $item->unit_price !== 0) {
                $item->update(['unit_price' => 0]);
            }
        }

        // Consumables.
        $forroSku = ($combo['forro'] ?? 'small') === 'large' ? 'ACC-FORRO-LARGE' : 'ACC-FORRO-SMALL';
        $this->addZeroLine($sale, $forroSku);
        $this->addZeroLine($sale, self::SKU_PANO);
        if (! empty($combo['include_liquid'])) {
            $this->addZeroLine($sale, self::SKU_LIQUIDO);
        }

        // Bag by merchandise total (subtotal - discount), before surcharge.
        $sale->recalculateTotals();
        $merch = max(0, (int) $sale->subtotal - (int) $sale->discount);
        $bagSku = $merch >= self::BAG_THRESHOLD ? 'ACC-BOLSA-PAPEL' : 'ACC-BOLSA-PLASTICO';
        $this->addZeroLine($sale, $bagSku);
    }

    /** Add the per-product `specs.includes` bundle SKUs (e.g. contact-lens solution). */
    private function applyIncludes(Sale $sale): void
    {
        $sale->load('items.product');
        foreach ($sale->items as $item) {
            $includes = $item->product?->specs['includes'] ?? null;
            if (is_array($includes)) {
                foreach ($includes as $sku) {
                    $this->addZeroLine($sale, $sku);
                }
            }
        }
    }

    /** A standalone frame/sunglasses sale (no lens) gets a funda. */
    private function applyFunda(Sale $sale): void
    {
        $hasFrame = $sale->items->contains(fn ($i) => $i->product?->category?->name === 'Montura');
        if ($hasFrame) {
            $this->addZeroLine($sale, self::SKU_FUNDA);
        }
    }

    /** Idempotently add a $0 line for a consumable SKU (decrements stock if stockable). */
    private function addZeroLine(Sale $sale, string $sku): void
    {
        $product = Product::where('sku', $sku)->first();
        if ($product === null || $sale->items()->where('product_id', $product->id)->exists()) {
            return;
        }
        $sale->items()->create([
            'product_id' => $product->id,
            'description' => $product->name,
            'quantity' => 1,
            'unit_price' => 0,
            'unit_cost' => $product->cost,
        ]);
        $sale->load('items.product.category');
    }
}
