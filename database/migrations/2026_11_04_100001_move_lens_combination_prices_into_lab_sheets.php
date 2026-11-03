<?php

use App\Models\Prescription;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Turn each laboratory's legacy cost rows into one price sheet: a column per
     * lens type, technology and addition range, a row per material and
     * sphere/cylinder range, and a cell per legacy row. Labs that already have
     * a sheet are skipped, and the legacy rows stay in place.
     */
    public function up(): void
    {
        $labIds = DB::table('lens_combination_prices')
            ->join('suppliers', 'suppliers.id', '=', 'lens_combination_prices.supplier_id')
            ->where('suppliers.is_laboratory', true)
            ->whereNotIn('suppliers.id', DB::table('lab_price_sheets')->select('supplier_id'))
            ->distinct()
            ->pluck('suppliers.id');

        foreach ($labIds as $labId) {
            $this->moveLab((int) $labId);
        }
    }

    /**
     * Build the sheet of one laboratory from its legacy rows (lowest id wins a duplicated cell).
     */
    private function moveLab(int $labId): void
    {
        $legacy = DB::table('lens_combination_prices as p')
            ->join('lens_combinations as c', 'c.id', '=', 'p.lens_combination_id')
            ->join('lens_types as t', 't.id', '=', 'c.lens_type_id')
            ->join('lens_technologies as g', 'g.id', '=', 'c.lens_technology_id')
            ->join('lens_materials as m', 'm.id', '=', 'c.lens_material_id')
            ->where('p.supplier_id', $labId)
            ->orderBy('p.id')
            ->get(['p.*', 'c.lens_type_id', 'c.lens_technology_id', 'c.lens_material_id', 't.name as type_name', 'g.name as technology_name', 'm.name as material_name']);

        if ($legacy->isEmpty()) {
            return;
        }

        $now = now();
        $companyId = $legacy->first()->company_id;
        $sheetId = DB::table('lab_price_sheets')->insertGetId([
            'company_id' => $companyId, 'supplier_id' => $labId, 'name' => 'Lista actual', 'created_at' => $now, 'updated_at' => $now,
        ]);

        $key = fn (mixed ...$values): string => implode('|', array_map(
            fn (mixed $value): string => $value === null ? 'null' : (string) (float) $value,
            $values,
        ));
        $number = fn (mixed $value): string => $value === null ? '…' : (string) (float) $value;

        $columnIds = [];
        $rowIds = [];
        $seenCells = [];

        foreach ($legacy as $price) {
            $columnKey = $key($price->lens_type_id, $price->lens_technology_id, $price->add_min, $price->add_max);
            $columnIds[$columnKey] ??= DB::table('lab_sheet_columns')->insertGetId([
                'company_id' => $companyId, 'lab_price_sheet_id' => $sheetId,
                'label' => trim("{$price->type_name} {$price->technology_name}")
                    .($price->add_min === null && $price->add_max === null ? '' : sprintf(' (add %s–%s)', $number($price->add_min), $number($price->add_max))),
                'lens_type_id' => $price->lens_type_id, 'lens_technology_id' => $price->lens_technology_id,
                'add_min' => $price->add_min, 'add_max' => $price->add_max,
                'sort_order' => count($columnIds), 'created_at' => $now, 'updated_at' => $now,
            ]);

            $rowKey = $key($price->lens_material_id, $price->sphere_min, $price->sphere_max, $price->cylinder_min, $price->cylinder_max);
            $isAllPrescriptions = [(float) $price->sphere_min, (float) $price->sphere_max, (float) $price->cylinder_min, (float) $price->cylinder_max]
                === [...Prescription::LIMITS['sphere'], ...Prescription::LIMITS['cylinder']];
            $rowIds[$rowKey] ??= DB::table('lab_sheet_rows')->insertGetId([
                'company_id' => $companyId, 'lab_price_sheet_id' => $sheetId,
                'label' => $price->material_name.($isAllPrescriptions ? '' : sprintf(' (esf %s/%s, cil %s/%s)', $number($price->sphere_min), $number($price->sphere_max), $number($price->cylinder_min), $number($price->cylinder_max))),
                'lens_material_id' => $price->lens_material_id,
                'sphere_min' => $price->sphere_min, 'sphere_max' => $price->sphere_max,
                'cylinder_min' => $price->cylinder_min, 'cylinder_max' => $price->cylinder_max,
                'sort_order' => count($rowIds), 'created_at' => $now, 'updated_at' => $now,
            ]);

            $cellKey = $rowIds[$rowKey].'|'.$columnIds[$columnKey];

            if (isset($seenCells[$cellKey])) {
                continue;
            }

            $seenCells[$cellKey] = true;
            DB::table('lab_sheet_cells')->insert([
                'company_id' => $companyId, 'lab_price_sheet_id' => $sheetId,
                'lab_sheet_row_id' => $rowIds[$rowKey], 'lab_sheet_column_id' => $columnIds[$columnKey],
                'lens_combination_id' => $price->lens_combination_id,
                'cost' => $price->cost, 'is_preferred' => $price->is_preferred, 'is_active' => $price->is_active,
                'created_at' => $now, 'updated_at' => $now,
            ]);
        }
    }

    /**
     * Sheets may have been edited since, so there is nothing safe to undo.
     */
    public function down(): void {}
};
