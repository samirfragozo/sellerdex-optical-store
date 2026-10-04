<?php

namespace App\Actions;

use App\Models\Company;
use App\Models\Sale;
use App\Models\SaleItem;
use Illuminate\Support\Facades\DB;

/**
 * Bring an expired quote to today's prices. Lens lines move by the change in their base price, so kit
 * surcharges and an approved override keep their offset; catalog lines take the current price; free combo
 * lines, armado slot lines and inactive products keep the quoted price.
 */
class RepriceQuote
{
    public function handle(Sale $quote): void
    {
        DB::transaction(function () use ($quote): void {
            $company = Company::withoutGlobalScopes()->findOrFail($quote->company_id);
            $items = $quote->items()->with(['product.category', 'lensConfig.treatments', 'lensConfig.prescription', 'lensConfig.lensCombination', 'lensOrder'])->get();

            foreach ($items as $item) {
                if ($item->lensConfig !== null) {
                    $this->repriceLens($item);

                    continue;
                }

                $product = $item->product;
                if ($product === null || ! $product->is_active) {
                    continue;
                }

                if ($item->group_key !== null && $product->category?->key === 'frame') {
                    $item->update(['unit_price' => $company->armadoFrameUnitPrice($product->price), 'unit_cost' => $product->cost]);
                } elseif ($item->group_key === null && $item->unit_price > 0) {
                    $item->update(['unit_price' => $product->price, 'unit_cost' => $product->cost]);
                }
            }

            $quote->refresh()->recalculateTotals();
        });
    }

    private function repriceLens(SaleItem $item): void
    {
        $config = $item->lensConfig;
        $combination = $config->lensCombination;

        $resolved = (new ResolveLensPricing)->handle(
            $combination->lens_type_id,
            $combination->lens_technology_id,
            $combination->lens_material_id,
            $config->treatments->pluck('lens_treatment_id')->all(),
            $config->prescription,
            $item->lensOrder?->supplier_id,
        );

        $quotedBase = $config->combination_price + $config->installation_price + (int) $config->treatments->sum('price');

        $item->update([
            'unit_price' => max(0, $item->unit_price - $quotedBase + $resolved['price']),
            'unit_cost' => $resolved['cost'],
        ]);

        $config->update([
            'combination_cost' => $resolved['price_row']->cost,
            'combination_price' => $resolved['price_row']->price,
            'installation_price' => $combination->installation_price,
        ]);

        foreach ($config->treatments as $treatment) {
            $current = $resolved['treatments']->firstWhere('id', $treatment->lens_treatment_id);
            $treatment->update(['price' => $current->price, 'cost' => $current->cost]);
        }
    }
}
