<?php

namespace App\Models;

use App\Enums\MixedRangePricing;
use App\Models\Concerns\HasPrescriptionRange;
use App\Support\Optics\LensOffer;
use App\Traits\BelongsToCompany;
use Database\Factories\LensCombinationPriceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Collection;

#[Fillable(['company_id', 'lens_combination_id', 'supplier_id', 'sphere_min', 'sphere_max', 'cylinder_min', 'cylinder_max', 'add_min', 'add_max', 'cost', 'price', 'is_preferred', 'is_active'])]
class LensCombinationPrice extends Model
{
    /** @use HasFactory<LensCombinationPriceFactory> */
    use BelongsToCompany, HasFactory, HasPrescriptionRange;

    /** The whole diopter scale: the "all prescriptions" range onboarding starts every lab with. */
    public const ALL_PRESCRIPTIONS = [
        'sphere_min' => Prescription::LIMITS['sphere'][0], 'sphere_max' => Prescription::LIMITS['sphere'][1],
        'cylinder_min' => Prescription::LIMITS['cylinder'][0], 'cylinder_max' => Prescription::LIMITS['cylinder'][1],
        'add_min' => null, 'add_max' => null,
    ];

    protected function casts(): array
    {
        return [
            'sphere_min' => 'decimal:2',
            'sphere_max' => 'decimal:2',
            'cylinder_min' => 'decimal:2',
            'cylinder_max' => 'decimal:2',
            'add_min' => 'decimal:2',
            'add_max' => 'decimal:2',
            'cost' => 'integer',
            'price' => 'integer',
            'is_preferred' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function lensCombination(): BelongsTo
    {
        return $this->belongsTo(LensCombination::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    /**
     * One offer per active lab that can make this lens for this
     * prescription (plano when there is none): each eye takes that lab's
     * narrowest active row covering it (ties: oldest row), and a lab
     * whose rows miss either eye makes no offer. Best first: preferred
     * lab — any of its active rows for this combination is preferred —
     * then the narrower pair, then the oldest row.
     *
     * @return Collection<int, LensOffer>
     */
    public static function offers(LensCombination $combination, ?Prescription $prescription): Collection
    {
        $refraction = $prescription?->refractionForRanges() ?? Prescription::PLANO_REFRACTION;

        return self::query()
            ->where('lens_combination_id', $combination->id)
            ->where('is_active', true)
            ->whereHas('supplier', fn (Builder $query) => $query->where('is_laboratory', true)->where('is_active', true))
            ->with('supplier')
            ->orderBy('id')
            ->get()
            ->groupBy('supplier_id')
            ->map(function (Collection $rows) use ($refraction): ?LensOffer {
                $odRow = self::narrowestCovering($rows, $refraction['od'], $refraction['add']);
                $osRow = self::narrowestCovering($rows, $refraction['os'], $refraction['add']);

                if ($odRow === null || $osRow === null) {
                    return null;
                }

                return new LensOffer(
                    supplier: $odRow->supplier,
                    odRow: $odRow,
                    osRow: $osRow,
                    cost: $odRow->supplier->mixed_range_pricing->combine($odRow->cost, $osRow->cost),
                    // ponytail: the company's own pricing rule arrives with price bands (spec §4 step 6).
                    price: MixedRangePricing::Highest->combine($odRow->price, $osRow->price),
                    isPreferred: $rows->contains('is_preferred', true),
                );
            })
            ->filter()
            ->sortBy([
                fn (LensOffer $a, LensOffer $b): int => $b->isPreferred <=> $a->isPreferred,
                fn (LensOffer $a, LensOffer $b): int => ($a->odRow->rangeWidth() + $a->osRow->rangeWidth()) <=> ($b->odRow->rangeWidth() + $b->osRow->rangeWidth()),
                fn (LensOffer $a, LensOffer $b): int => min($a->odRow->id, $a->osRow->id) <=> min($b->odRow->id, $b->osRow->id),
            ])
            ->values();
    }

    /** The offer that prices this lens: at $supplierId when given, otherwise the best one. */
    public static function resolve(LensCombination $combination, ?Prescription $prescription, ?int $supplierId = null): ?LensOffer
    {
        return self::offers($combination, $prescription)
            ->first(fn (LensOffer $offer): bool => $supplierId === null || $offer->supplier->id === $supplierId);
    }

    /** "Prescription out of range for <combination> at <lab>" (or at every lab). */
    public static function outOfRangeMessage(LensCombination $combination, ?Supplier $lab): string
    {
        return $lab === null
            ? __('app.pos.lens_form.out_of_range_any', ['combination' => $combination->label()])
            : __('app.pos.lens_form.out_of_range', ['combination' => $combination->label(), 'lab' => $lab->name]);
    }
}
