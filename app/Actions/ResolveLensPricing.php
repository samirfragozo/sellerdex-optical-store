<?php

namespace App\Actions;

use App\Models\LensCombination;
use App\Models\LensTreatment;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * Validate a chosen lens configuration (Tipo, Tecnología, Material,
 * Tratamientos) and resolve its total sellable price/cost.
 */
class ResolveLensPricing
{
    /**
     * @param  array<int, int>  $treatmentIds
     * @return array{combination: LensCombination, treatments: Collection<int, LensTreatment>, price: int, cost: int}
     */
    public function handle(int $lensTypeId, int $lensTechnologyId, int $lensMaterialId, array $treatmentIds): array
    {
        $combination = LensCombination::forSelection($lensTypeId, $lensTechnologyId, $lensMaterialId);

        if ($combination === null) {
            throw ValidationException::withMessages([
                'lens' => __('app.pos.lens_form.invalid_combination'),
            ]);
        }

        $treatments = LensTreatment::query()
            ->whereIn('id', $treatmentIds)
            ->where('is_active', true)
            ->get();

        $uniqueTreatmentIds = array_unique($treatmentIds);
        if (count($treatmentIds) !== count($uniqueTreatmentIds) || $treatments->count() !== count($uniqueTreatmentIds)) {
            throw ValidationException::withMessages([
                'lens' => __('app.pos.lens_form.invalid_treatment'),
            ]);
        }

        $price = $combination->price + $combination->installation_price + (int) $treatments->sum('price');
        $cost = $combination->cost + (int) $treatments->sum('cost');

        return compact('combination', 'treatments', 'price', 'cost');
    }
}
