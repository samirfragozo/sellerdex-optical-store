<?php

namespace App\Models;

use App\Enums\MixedRangePricing;
use App\Models\Concerns\HasPrescriptionRange;
use App\Traits\BelongsToCompany;
use Database\Factories\LensPriceBandFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['company_id', 'lens_combination_id', 'sphere_min', 'sphere_max', 'cylinder_min', 'cylinder_max', 'add_min', 'add_max', 'price'])]
class LensPriceBand extends Model
{
    /** @use HasFactory<LensPriceBandFactory> */
    use BelongsToCompany, HasFactory, HasPrescriptionRange;

    protected function casts(): array
    {
        return [
            'sphere_min' => 'decimal:2',
            'sphere_max' => 'decimal:2',
            'cylinder_min' => 'decimal:2',
            'cylinder_max' => 'decimal:2',
            'add_min' => 'decimal:2',
            'add_max' => 'decimal:2',
            'price' => 'integer',
        ];
    }

    public function lensCombination(): BelongsTo
    {
        return $this->belongsTo(LensCombination::class);
    }

    /**
     * The sale price of this lens for a prescription: each eye takes its
     * narrowest covering band and $rule combines the two prices. Null when
     * either eye has no covering band.
     *
     * @param  array{od: array{sphere: float, cylinder: float}, os: array{sphere: float, cylinder: float}, add: float}  $refraction
     */
    public static function priceFor(LensCombination $combination, array $refraction, MixedRangePricing $rule): ?int
    {
        $bands = $combination->priceBands()->orderBy('id')->get();
        $odBand = self::narrowestCovering($bands, $refraction['od'], $refraction['add']);
        $osBand = self::narrowestCovering($bands, $refraction['os'], $refraction['add']);

        if ($odBand === null || $osBand === null) {
            return null;
        }

        return $rule->combine($odBand->price, $osBand->price);
    }
}
