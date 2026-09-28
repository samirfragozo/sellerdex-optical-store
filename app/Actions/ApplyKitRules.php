<?php

namespace App\Actions;

use App\Enums\KitTrigger;
use App\Models\KitSlot;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;

/** Turns a company's combo slots into sale lines. The only place combo rules live. */
class ApplyKitRules
{
    /** @param  list<array{kit_slot_id: int, product_id?: int|null, selected?: bool}>  $selections */
    public function forArmado(Sale $sale, string $groupKey, array $selections, User $seller): void
    {
        $chosen = collect($selections)->keyBy('kit_slot_id');
        $lensLine = $sale->items()->where('group_key', $groupKey)->whereHas('lensConfig')->first();

        foreach ($this->activeSlots($seller)->where('trigger', KitTrigger::Armado)->get() as $slot) {
            $selection = $chosen->get($slot->id);
            $selected = $slot->is_optional ? (bool) ($selection['selected'] ?? $slot->is_preselected) : true;
            if (! $selected) {
                continue;
            }

            $product = $this->productFor($slot, $selection['product_id'] ?? null);
            if ($product === null) {
                continue;
            }
            $this->addLine($sale, $slot, $product, $groupKey, $slot->unitPriceFor($product), $seller);

            if ($lensLine !== null && $slot->lensSurcharge() > 0) {
                $lensLine->update(['unit_price' => $lensLine->unit_price + $slot->lensSurcharge()]);
            }
        }
    }

    /**
     * Category slots for products sold outside an armado, and product slots for any
     * sold product. Each slot adds its line once per sale.
     */
    public function forStandaloneLines(Sale $sale, User $seller): void
    {
        $lines = $sale->items()->whereNotNull('product_id')->with('product')->get();
        $looseCategoryIds = $lines->whereNull('group_key')->pluck('product.product_category_id')->filter()->unique()->values();
        $productIds = $lines->pluck('product_id')->unique()->values();

        $slots = $this->activeSlots($seller)
            ->where(fn (Builder $query) => $query
                ->where(fn (Builder $query) => $query->where('trigger', KitTrigger::Category)->whereIn('trigger_category_id', $looseCategoryIds))
                ->orWhere(fn (Builder $query) => $query->where('trigger', KitTrigger::Product)->whereIn('trigger_product_id', $productIds)))
            ->get();

        foreach ($slots as $slot) {
            $this->addLineOnce($sale, $slot, $this->active($slot->defaultProduct), $seller);
        }
    }

    /** Sale slots (e.g. the bag), choosing the upgrade product by merchandise total. */
    public function forSale(Sale $sale, User $seller): void
    {
        $sale->recalculateTotals();
        $merchTotal = max(0, (int) $sale->subtotal - (int) $sale->discount);

        foreach ($this->activeSlots($seller)->where('trigger', KitTrigger::Sale)->with('upgradeProduct')->get() as $slot) {
            $this->addLineOnce($sale, $slot, $this->active($slot->productForMerchTotal($merchTotal)), $seller);
        }
    }

    /** @return Builder<KitSlot> */
    private function activeSlots(User $seller): Builder
    {
        return KitSlot::query()
            ->where('company_id', $seller->company_id)
            ->where('is_active', true)
            ->orderBy('sort_order')->orderBy('id')
            ->with('defaultProduct');
    }

    /** The selected product when it is active and belongs to the slot category, else the active slot default. */
    private function productFor(KitSlot $slot, ?int $productId): ?Product
    {
        if ($productId === null) {
            return $this->active($slot->defaultProduct);
        }

        $product = Product::query()->whereKey($productId)->where('is_active', true)
            ->where('product_category_id', $slot->slot_category_id)->first();

        if ($product === null) {
            throw ValidationException::withMessages(['armados' => __('app.pos.kit.invalid_product')]);
        }

        return $product;
    }

    private function active(?Product $product): ?Product
    {
        return $product?->is_active ? $product : null;
    }

    /**
     * Add a free sale-level (ungrouped) slot line unless that product is already on the sale outside an armado.
     * Only armado slots may charge: the POS never shows a price for these, whatever mode is stored.
     */
    private function addLineOnce(Sale $sale, KitSlot $slot, ?Product $product, User $seller): void
    {
        if ($product === null || $this->alreadyHas($sale, $product->id)) {
            return;
        }

        $this->addLine($sale, $slot, $product, null, 0, $seller);
    }

    private function alreadyHas(Sale $sale, int $productId): bool
    {
        return $sale->items()->where('product_id', $productId)->whereNull('group_key')->exists();
    }

    private function addLine(Sale $sale, KitSlot $slot, Product $product, ?string $groupKey, int $unitPrice, User $seller): void
    {
        $sale->items()->create([
            'group_key' => $groupKey,
            'product_id' => $product->id,
            'description' => $product->name,
            'quantity' => $slot->quantity,
            'unit_price' => $unitPrice,
            'unit_cost' => $product->cost,
            ...SaleItem::taxSnapshotFor($product, $seller->company),
        ]);
    }
}
