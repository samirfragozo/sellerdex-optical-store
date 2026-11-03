<?php

namespace App\Models;

use App\Scopes\CompanyScope;
use App\Traits\BelongsToCompany;
use Database\Factories\LensCombinationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

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

    /** Lab cost of this combination per lab and prescription range (the sale price lives in the price bands). */
    public function prices(): HasMany
    {
        return $this->hasMany(LensCombinationPrice::class);
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
     * an active cost row at an active, non-deleted lab. The closures drop the
     * company scope so readiness checks can run for a given company without an
     * authenticated user.
     *
     * @param  Builder<self>  $query
     */
    public function scopeSellable(Builder $query): void
    {
        $query->where('is_active', true)
            ->whereHas('priceBands', fn (Builder $band) => $band->withoutGlobalScopes()->where('price', '>', 0))
            ->whereHas('prices', fn (Builder $row) => $row->withoutGlobalScopes()->where('is_active', true)
                ->whereHas('supplier', fn (Builder $lab) => $lab->withoutGlobalScope(CompanyScope::class)->where('is_laboratory', true)->where('is_active', true)));
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
