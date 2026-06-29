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
                'customer_id' => $data['customer_id'] ?? null,
                'seller_id' => $seller->id,
                'created_by' => $seller->id,
                'prescription_id' => $data['prescription_id'] ?? null,
                'document_type' => $data['document_type'] ?? 'order',
                'discount' => $data['discount'] ?? 0,
                'surcharge_percent' => $this->resolveSurcharge($data),
                'sold_at' => $data['sold_at'] ?? now()->toDateString(),
                'notes' => $data['notes'] ?? null,
            ]);

            if (array_key_exists('items', $data)) {
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
            } else {
                $this->buildArmados($sale, $data['armados'] ?? []);
                foreach ($data['products'] ?? [] as $product) {
                    $sale->items()->create([
                        'product_id' => $product['product_id'] ?? null,
                        'description' => $product['description'],
                        'quantity' => $product['quantity'] ?? 1,
                        'unit_price' => $product['unit_price'],
                        'unit_cost' => $product['unit_cost'] ?? 0,
                    ]);
                }
                $this->applyIncludes($sale);
                if (empty($data['armados'])) {
                    $sale->load('items.product.category');
                    $this->applyFunda($sale);
                }
                $this->applyBag($sale);
            }

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
        $lensLine = $sale->items->first(fn ($i) => $i->product?->category?->key === 'lens');

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
            if ($item->product?->category?->key === 'frame' && $item->unit_price !== 0) {
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
        $this->applyBag($sale);
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

            $sale->items()->create([
                'group_key' => $groupKey,
                'product_id' => $lens['product_id'] ?? null,
                'description' => $lens['description'],
                'quantity' => $lens['quantity'] ?? 1,
                'unit_price' => $lens['unit_price'],
                'unit_cost' => $lens['unit_cost'] ?? 0,
            ]);

            if (empty($armado['own_frame']) && ! empty($armado['frame'])) {
                $frame = $armado['frame'];
                $sale->items()->create([
                    'group_key' => $groupKey,
                    'product_id' => $frame['product_id'] ?? null,
                    'description' => $frame['description'],
                    'quantity' => $frame['quantity'] ?? 1,
                    'unit_price' => $frame['unit_price'],
                    'unit_cost' => $frame['unit_cost'] ?? 0,
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
        $combo ??= ['forro' => 'small', 'include_liquid' => false, 'with_exam' => false];
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

        $forroSku = ($combo['forro'] ?? 'small') === 'large' ? 'ACC-FORRO-LARGE' : 'ACC-FORRO-SMALL';
        $this->addZeroLine($sale, $forroSku, $groupKey);
        $this->addZeroLine($sale, self::SKU_PANO, $groupKey);
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
        ]);
        $sale->load('items.product.category');
    }
}
