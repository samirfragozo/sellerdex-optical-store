<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Turn each combination's active lab sale prices into lens price bands: one
     * band per distinct range, priced from the preferred lab when it has that
     * range, otherwise at the highest lab price for it.
     */
    public function up(): void
    {
        $activeLabRows = fn () => DB::table('lens_combination_prices')
            ->join('suppliers', 'suppliers.id', '=', 'lens_combination_prices.supplier_id')
            ->where('suppliers.is_laboratory', true)
            ->where('suppliers.is_active', true)
            ->whereNull('suppliers.deleted_at')
            ->where('lens_combination_prices.is_active', true);

        $companyIds = DB::table('lens_combinations')->pluck('company_id', 'id');

        $preferredSupplierIds = $activeLabRows()
            ->where('lens_combination_prices.is_preferred', true)
            ->groupBy('lens_combination_prices.lens_combination_id')
            ->selectRaw('lens_combination_prices.lens_combination_id as combination_id, min(lens_combination_prices.supplier_id) as supplier_id')
            ->pluck('supplier_id', 'combination_id');

        $rows = $activeLabRows()
            ->where('lens_combination_prices.price', '>', 0)
            ->whereNotIn('lens_combination_prices.lens_combination_id', DB::table('lens_price_bands')->select('lens_combination_id'))
            ->orderBy('lens_combination_prices.id')
            ->select('lens_combination_prices.*')
            ->get();

        foreach ($rows->groupBy('lens_combination_id') as $combinationId => $combinationRows) {
            $preferredSupplierId = $preferredSupplierIds[$combinationId] ?? null;

            $groups = $combinationRows->groupBy(fn (object $row): string => implode('|', array_map(
                fn (mixed $value): string => $value === null ? 'null' : (string) (float) $value,
                [$row->sphere_min, $row->sphere_max, $row->cylinder_min, $row->cylinder_max, $row->add_min, $row->add_max],
            )));

            foreach ($groups as $group) {
                $first = $group->first();
                $preferredRow = $preferredSupplierId === null ? null : $group->firstWhere('supplier_id', $preferredSupplierId);

                DB::table('lens_price_bands')->insert([
                    'company_id' => $companyIds[$combinationId],
                    'lens_combination_id' => $combinationId,
                    'sphere_min' => $first->sphere_min,
                    'sphere_max' => $first->sphere_max,
                    'cylinder_min' => $first->cylinder_min,
                    'cylinder_max' => $first->cylinder_max,
                    'add_min' => $first->add_min,
                    'add_max' => $first->add_max,
                    'price' => $preferredRow?->price ?? $group->max('price'),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    /**
     * Bands may have been edited since, so there is nothing safe to undo.
     */
    public function down(): void {}
};
