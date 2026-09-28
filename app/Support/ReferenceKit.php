<?php

namespace App\Support;

use App\Enums\KitPriceMode;
use App\Enums\KitTrigger;
use App\Models\Company;
use App\Models\KitSlot;
use App\Models\Product;
use App\Models\ProductCategory;
use Illuminate\Support\Collection;

/**
 * The suggested combo slots, built from a company's own categories and products.
 * They reproduce the rules that used to be hardcoded in the sale flow. Slots whose
 * category has no active product are skipped; running it again creates nothing new.
 */
class ReferenceKit
{
    public static function installFor(Company $company): void
    {
        $armado = [
            ['key' => 'service', 'price_mode' => KitPriceMode::AddedToLens, 'price_value' => 20_000, 'is_optional' => true, 'is_preselected' => false],
            ['key' => 'case', 'price_mode' => KitPriceMode::Free, 'is_optional' => false],
            ['key' => 'cloth', 'price_mode' => KitPriceMode::Free, 'is_optional' => true, 'is_preselected' => true],
            ['key' => 'cleaning', 'price_mode' => KitPriceMode::Free, 'is_optional' => true, 'is_preselected' => false],
        ];

        foreach ($armado as $sortOrder => $slot) {
            self::install($company, KitTrigger::Armado, null, $slot, $sortOrder);
        }

        $frame = self::category($company, 'frame');
        if ($frame !== null) {
            self::install($company, KitTrigger::Category, $frame, ['key' => 'pouch', 'price_mode' => KitPriceMode::Free, 'is_optional' => false], 0);
        }

        self::install($company, KitTrigger::Sale, null, ['key' => 'bag', 'price_mode' => KitPriceMode::Free, 'is_optional' => false, 'upgrade_min_total' => 215_000], 0);
    }

    /** @param array{key: string, price_mode: KitPriceMode, price_value?: int, is_optional: bool, is_preselected?: bool, upgrade_min_total?: int} $slot */
    private static function install(Company $company, KitTrigger $trigger, ?ProductCategory $triggerCategory, array $slot, int $sortOrder): void
    {
        $category = self::category($company, $slot['key']);
        $products = $category === null ? collect() : self::activeProducts($company, $category);

        if ($products->isEmpty()) {
            return;
        }

        $default = $slot['key'] === 'service'
            ? ($products->firstWhere('name', 'Examen visual') ?? $products->first())
            : $products->first();
        $upgrade = isset($slot['upgrade_min_total']) && $products->last()->isNot($default) ? $products->last() : null;

        KitSlot::withoutGlobalScopes()->firstOrCreate([
            'company_id' => $company->id,
            'scope_key' => $trigger === KitTrigger::Category ? 'category:'.$triggerCategory->id : $trigger->value,
            'slot_category_id' => $category->id,
        ], [
            'trigger' => $trigger,
            'trigger_category_id' => $triggerCategory?->id,
            'default_product_id' => $default->id,
            'quantity' => 1,
            'price_mode' => $slot['price_mode'],
            'price_value' => $slot['price_value'] ?? 0,
            'is_optional' => $slot['is_optional'],
            'is_preselected' => $slot['is_preselected'] ?? true,
            'upgrade_product_id' => $upgrade?->id,
            'upgrade_min_total' => $upgrade !== null ? $slot['upgrade_min_total'] : null,
            'is_active' => true,
            'sort_order' => $sortOrder,
        ]);
    }

    private static function category(Company $company, string $key): ?ProductCategory
    {
        return ProductCategory::withoutGlobalScopes()->where('company_id', $company->id)->where('key', $key)->first();
    }

    /** @return Collection<int, Product> cheapest first */
    private static function activeProducts(Company $company, ProductCategory $category): Collection
    {
        return Product::withoutGlobalScopes()
            ->where('company_id', $company->id)
            ->where('product_category_id', $category->id)
            ->where('is_active', true)
            ->whereNull('base_product_id')
            ->whereNull('deleted_at')
            ->orderBy('price')
            ->get();
    }
}
