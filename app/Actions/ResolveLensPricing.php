<?php

namespace App\Actions;

use App\Models\LensCombination;
use App\Models\LensTreatment;
use App\Models\Prescription;
use App\Models\Supplier;
use App\Support\Optics\LensOffer;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * Validate a chosen lens configuration (Tipo, Tecnología, Material,
 * Tratamientos) and resolve its total sellable price/cost: the lens price
 * comes from the lens price bands, the cost from the sheet cells of the lab offer
 * covering both eyes of the prescription (plano when there is none) at the
 * chosen lab, or at the preferred one.
 */
class ResolveLensPricing
{
    /**
     * @param  array<int, int>  $treatmentIds
     * @return array{combination: LensCombination, treatments: Collection<int, LensTreatment>, offer: LensOffer, price: int, cost: int}
     */
    public function handle(int $lensTypeId, int $lensTechnologyId, int $lensMaterialId, array $treatmentIds, ?Prescription $prescription = null, ?int $supplierId = null): array
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

        $offer = $combination->resolveOffer($prescription, $supplierId);

        if ($offer === null) {
            $combination->loadMissing(['lensType', 'lensTechnology', 'lensMaterial']);

            throw ValidationException::withMessages([
                'lens' => $combination->outOfRangeMessage($supplierId !== null ? Supplier::find($supplierId) : null, $prescription),
            ]);
        }

        $price = $offer->price + $combination->installation_price + (int) $treatments->sum('price');
        $cost = $offer->cost + (int) $treatments->sum('cost');

        return ['combination' => $combination, 'treatments' => $treatments, 'offer' => $offer, 'price' => $price, 'cost' => $cost];
    }
}
