<?php

namespace App\Actions;

use App\Models\LabPriceSheet;
use App\Models\Supplier;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/** Starts a lab's sheet from another sheet's columns and rows; the costs are left for the user to fill in. */
class CopyLabPriceSheet
{
    public function handle(LabPriceSheet $source, Supplier $lab, string $name): LabPriceSheet
    {
        if ($source->company_id !== $lab->company_id) {
            throw new InvalidArgumentException('The source sheet belongs to another company.');
        }

        return DB::transaction(function () use ($source, $lab, $name): LabPriceSheet {
            $sheet = LabPriceSheet::create(['company_id' => $lab->company_id, 'supplier_id' => $lab->id, 'name' => $name]);

            foreach ($source->columns as $column) {
                $sheet->columns()->create(['company_id' => $lab->company_id, ...$column->only(['label', 'lens_type_id', 'lens_technology_id', 'add_min', 'add_max', 'sort_order'])]);
            }

            foreach ($source->rows as $row) {
                $sheet->rows()->create(['company_id' => $lab->company_id, ...$row->only(['label', 'lens_material_id', 'sphere_min', 'sphere_max', 'cylinder_min', 'cylinder_max', 'sort_order'])]);
            }

            return $sheet;
        });
    }
}
