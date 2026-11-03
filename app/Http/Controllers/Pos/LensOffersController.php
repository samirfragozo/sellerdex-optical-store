<?php

namespace App\Http\Controllers\Pos;

use App\Http\Controllers\Controller;
use App\Models\LensCombination;
use App\Models\Prescription;
use App\Support\Optics\LensOffer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class LensOffersController extends Controller
{
    /**
     * The labs that can make this lens for this prescription — preferred
     * first; each offer carries the lens band price (same at every lab) and
     * that lab's cost — or why none can, so the POS can say so before the sale.
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

        $prescription = Prescription::findOrFail($validated['prescription_id']);
        $offers = $combination->offers($prescription);
        $combination->load(['lensType', 'lensTechnology', 'lensMaterial']);

        return response()->json([
            'offers' => $offers->map(fn (LensOffer $offer): array => [
                'supplier_id' => $offer->supplier->id,
                'supplier_name' => $offer->supplier->name,
                'price' => $offer->price + $combination->installation_price,
                'cost' => $offer->cost,
                'is_preferred' => $offer->isPreferred,
            ])->all(),
            'message' => $offers->isEmpty() ? $combination->outOfRangeMessage(null, $prescription) : null,
        ]);
    }
}
