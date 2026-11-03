<?php

namespace App\Models;

use App\Scopes\CompanyScope;
use App\Support\Optics\LensOffer;
use App\Traits\BelongsToCompany;
use Database\Factories\LensCombinationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

#[Fillable(['company_id', 'lens_type_id', 'lens_technology_id', 'lens_material_id', 'installation_price', 'tax_id', 'is_active'])]
class LensCombination extends Model
{
    /** @use HasFactory<LensCombinationFactory> */
    use BelongsToCompany, HasFactory;

    protected function casts(): array
    {
        return [
            'installation_price' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function lensType(): BelongsTo
    {
        return $this->belongsTo(LensType::class);
    }

    public function lensTechnology(): BelongsTo
    {
        return $this->belongsTo(LensTechnology::class);
    }

    public function lensMaterial(): BelongsTo
    {
        return $this->belongsTo(LensMaterial::class);
    }

    public function tax(): BelongsTo
    {
        return $this->belongsTo(Tax::class);
    }

    /** What the shop charges for this combination per prescription range. */
    public function priceBands(): HasMany
    {
        return $this->hasMany(LensPriceBand::class);
    }

    /** The lab price sheet cells costing this combination. */
    public function cells(): HasMany
    {
        return $this->hasMany(LabSheetCell::class);
    }

    /**
     * Active combinations the POS can sell: a sale price band above zero and
     * an active sheet cell at an active, non-deleted lab. The closures drop the
     * company scope so readiness checks can run for a given company without an
     * authenticated user.
     *
     * @param  Builder<self>  $query
     */
    public function scopeSellable(Builder $query): void
    {
        $query->where('is_active', true)
            ->whereHas('priceBands', fn (Builder $band) => $band->withoutGlobalScopes()->where('price', '>', 0))
            ->whereHas('cells', fn (Builder $cell) => $cell->withoutGlobalScopes()->where('is_active', true)
                ->whereHas('sheet', fn (Builder $sheet) => $sheet->withoutGlobalScopes()
                    ->whereHas('supplier', fn (Builder $lab) => $lab->withoutGlobalScope(CompanyScope::class)->where('is_laboratory', true)->where('is_active', true))));
    }

    /**
     * One offer per active lab that can make this lens for this
     * prescription (plano when there is none): each eye takes that lab's
     * narrowest active cell covering it, across all of its sheets (ties:
     * oldest cell), and a lab whose cells miss either eye makes no offer.
     * Every offer sells at the lens's band price under the company's pair
     * rule; no covering band means no offers at all. Best first: preferred
     * lab — any of its active cells for this lens is preferred — then the
     * narrower pair, then the oldest cell.
     *
     * @return Collection<int, LensOffer>
     */
    public function offers(?Prescription $prescription): Collection
    {
        $refraction = $prescription?->refractionForRanges() ?? Prescription::PLANO_REFRACTION;
        $price = LensPriceBand::priceFor($this, $refraction, $this->company->mixed_range_pricing);

        if ($price === null) {
            return collect();
        }

        return $this->cells()
            ->where('is_active', true)
            ->whereHas('sheet.supplier', fn (Builder $lab) => $lab->where('is_laboratory', true)->where('is_active', true))
            ->with(['row', 'column', 'sheet.supplier'])
            ->orderBy('id')
            ->get()
            ->groupBy(fn (LabSheetCell $cell): int => $cell->sheet->supplier_id)
            ->map(function (Collection $cells) use ($refraction, $price): ?LensOffer {
                $odCell = LabSheetCell::narrowestCovering($cells, $refraction['od'], $refraction['add']);
                $osCell = LabSheetCell::narrowestCovering($cells, $refraction['os'], $refraction['add']);

                if ($odCell === null || $osCell === null) {
                    return null;
                }

                $lab = $odCell->sheet->supplier;

                return new LensOffer(
                    supplier: $lab,
                    odCell: $odCell,
                    osCell: $osCell,
                    cost: $lab->mixed_range_pricing->combine($odCell->cost, $osCell->cost),
                    price: $price,
                    isPreferred: $cells->contains('is_preferred', true),
                );
            })
            ->filter()
            ->sortBy([
                fn (LensOffer $a, LensOffer $b): int => $b->isPreferred <=> $a->isPreferred,
                fn (LensOffer $a, LensOffer $b): int => ($a->odCell->rangeWidth() + $a->osCell->rangeWidth()) <=> ($b->odCell->rangeWidth() + $b->osCell->rangeWidth()),
                fn (LensOffer $a, LensOffer $b): int => min($a->odCell->id, $a->osCell->id) <=> min($b->odCell->id, $b->osCell->id),
            ])
            ->values();
    }

    /** The offer that prices this lens: at $supplierId when given, otherwise the best one. */
    public function resolveOffer(?Prescription $prescription, ?int $supplierId = null): ?LensOffer
    {
        return $this->offers($prescription)
            ->first(fn (LensOffer $offer): bool => $supplierId === null || $offer->supplier->id === $supplierId);
    }

    /** "Prescription out of range for <combination> at <lab>" (or at every lab). */
    public function outOfRangeMessage(?Supplier $lab): string
    {
        return $lab === null
            ? __('app.pos.lens_form.out_of_range_any', ['combination' => $this->label()])
            : __('app.pos.lens_form.out_of_range', ['combination' => $this->label(), 'lab' => $lab->name]);
    }

    /** "Progresivo Digital Policarbonato" — how sellers and labs name the lens. */
    public function label(): string
    {
        return trim("{$this->lensType?->name} {$this->lensTechnology?->name} {$this->lensMaterial?->name}");
    }

    /** The active, sellable combination for a given Tipo×Tecnología×Material triple, if any. */
    public static function forSelection(int $lensTypeId, int $lensTechnologyId, int $lensMaterialId): ?self
    {
        return self::query()
            ->where('lens_type_id', $lensTypeId)
            ->where('lens_technology_id', $lensTechnologyId)
            ->where('lens_material_id', $lensMaterialId)
            ->where('is_active', true)
            ->first();
    }
}
