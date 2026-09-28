<?php

namespace App\Actions;

use App\Enums\KitTrigger;
use App\Models\KitSlot;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\User;
use Illuminate\Validation\ValidationException;

/** Turns a company's combo slots into sale lines. The only place combo rules live. */
class ApplyKitRules
{
    /** @param  list<array{kit_slot_id: int, product_id?: int|null, selected?: bool}>  $selections */
    public function forArmado(Sale $sale, string $groupKey, array $selections, User $seller): void
    {
        $chosen = collect($selections)->keyBy('kit_slot_id');
        $lensLine = $sale->items()->where('group_key', $groupKey)->whereHas('lensConfig')->first();

        $slots = KitSlot::query()
            ->where('company_id', $seller->company_id)
            ->where('trigger', KitTrigger::Armado)
            ->where('is_active', true)
            ->orderBy('sort_order')->orderBy('id')
            ->with('defaultProduct')
            ->get();

        foreach ($slots as $slot) {
            $selection = $chosen->get($slot->id);
            $selected = $slot->is_optional ? (bool) ($selection['selected'] ?? $slot->is_preselected) : true;
            if (! $selected) {
                continue;
            }

            $product = $this->productFor($slot, $selection['product_id'] ?? null);
            $this->addLine($sale, $slot, $product, $groupKey, $seller);

            if ($lensLine !== null && $slot->lensSurcharge() > 0) {
                $lensLine->update(['unit_price' => $lensLine->unit_price + $slot->lensSurcharge()]);
            }
        }
    }

    /** The selected product when it belongs to the slot category, else the slot default. */
    private function productFor(KitSlot $slot, ?int $productId): Product
    {
        if ($productId === null) {
            return $slot->defaultProduct;
        }

        $product = Product::query()->whereKey($productId)->where('is_active', true)
            ->where('product_category_id', $slot->slot_category_id)->first();

        if ($product === null) {
            throw ValidationException::withMessages(['armados' => __('app.pos.kit.invalid_product')]);
        }

        return $product;
    }

    private function addLine(Sale $sale, KitSlot $slot, Product $product, ?string $groupKey, User $seller): void
    {
        $sale->items()->create([
            'group_key' => $groupKey,
            'product_id' => $product->id,
            'description' => $product->name,
            'quantity' => $slot->quantity,
            'unit_price' => $slot->unitPriceFor($product),
            'unit_cost' => $product->cost,
            ...SaleItem::taxSnapshotFor($product, $seller->company),
        ]);
    }
}
