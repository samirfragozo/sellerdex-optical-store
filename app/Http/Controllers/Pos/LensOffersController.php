<?php

namespace App\Http\Controllers\Pos;

use App\Http\Controllers\Controller;
use App\Models\LensCombination;
use App\Models\LensCombinationPrice;
use App\Models\Prescription;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class LensOffersController extends Controller
{
    /**
     * The labs that can make this lens for this prescription — preferred
     * first, priced by the range of the prescription's governing eye —
     * or why none can, so the POS can say so before the sale.
     */
    public function __invoke(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'lens_type_id' => ['required', 'integer'],
            'lens_technology_id' => ['required', 'integer'],
            'lens_material_id' => ['required', 'integer'],
            'prescription_id' => ['required', 'integer', Rule::exists('prescriptions', 'id')
                ->where('company_id', $request->user()->company_id)->withoutTrashed()],
        ]);

        $combination = LensCombination::forSelection(
            (int) $validated['lens_type_id'], (int) $validated['lens_technology_id'], (int) $validated['lens_material_id'],
        );

        if ($combination === null) {
            return response()->json(['offers' => [], 'message' => __('app.pos.lens_form.invalid_combination')]);
        }

        $offers = LensCombinationPrice::offers($combination, Prescription::findOrFail($validated['prescription_id']));
        $combination->load(['lensType', 'lensTechnology', 'lensMaterial']);

        return response()->json([
            'offers' => $offers->map(fn (LensCombinationPrice $row): array => [
                'supplier_id' => $row->supplier_id,
                'supplier_name' => $row->supplier->name,
                'price' => $row->price + $combination->installation_price,
                'cost' => $row->cost,
                'is_preferred' => $row->is_preferred,
            ])->all(),
            'message' => $offers->isEmpty() ? LensCombinationPrice::outOfRangeMessage($combination, null) : null,
        ]);
    }
}
