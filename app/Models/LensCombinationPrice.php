<?php

namespace App\Models;

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
    use BelongsToCompany, HasFactory;

    /** The whole diopter scale: the "all prescriptions" range onboarding starts every lab with. */
    public const ALL_PRESCRIPTIONS = [
        'sphere_min' => -20, 'sphere_max' => 20,
        'cylinder_min' => -10, 'cylinder_max' => 10,
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
     * Active rows of $combination, at active labs, whose range covers the
     * prescription's governing eye (plano when there is no prescription) —
     * best first: preferred lab first — a lab is preferred when any of its
     * active rows for this combination is marked preferred — then the
     * narrowest range (so a specific high-power range beats "all
     * prescriptions"), then the oldest row.
     *
     * @return Builder<self>
     */
    public static function matching(LensCombination $combination, ?Prescription $prescription): Builder
    {
        ['sphere' => $sphere, 'cylinder' => $cylinder, 'add' => $add] = $prescription?->governingEye()
            ?? ['sphere' => 0.0, 'cylinder' => 0.0, 'add' => 0.0];

        return self::query()
            ->where('lens_combination_id', $combination->id)
            ->where('is_active', true)
            ->whereHas('supplier', fn (Builder $query) => $query->where('is_laboratory', true)->where('is_active', true))
            ->where('sphere_min', '<=', $sphere)->where('sphere_max', '>=', $sphere)
            ->where('cylinder_min', '<=', $cylinder)->where('cylinder_max', '>=', $cylinder)
            ->where(fn (Builder $query) => $query->whereNull('add_min')->orWhere('add_min', '<=', $add))
            ->where(fn (Builder $query) => $query->whereNull('add_max')->orWhere('add_max', '>=', $add))
            ->orderByRaw('(select max(p2.is_preferred) from lens_combination_prices as p2 where p2.lens_combination_id = lens_combination_prices.lens_combination_id and p2.supplier_id = lens_combination_prices.supplier_id and p2.is_active = 1) desc')
            ->orderByRaw('(sphere_max - sphere_min) + (cylinder_max - cylinder_min)')
            ->orderBy('id');
    }

    /** The row that prices this lens: at $supplierId when given, otherwise at the preferred lab. */
    public static function resolve(LensCombination $combination, ?Prescription $prescription, ?int $supplierId = null): ?self
    {
        return self::matching($combination, $prescription)
            ->when($supplierId !== null, fn (Builder $query) => $query->where('supplier_id', $supplierId))
            ->first();
    }

    /**
     * One offer per lab able to make this lens for this prescription, preferred first.
     *
     * @return Collection<int, self>
     */
    public static function offers(LensCombination $combination, ?Prescription $prescription): Collection
    {
        return self::matching($combination, $prescription)->with('supplier')->get()->unique('supplier_id')->values();
    }

    /** "Prescription out of range for <combination> at <lab>" (or at every lab). */
    public static function outOfRangeMessage(LensCombination $combination, ?Supplier $lab): string
    {
        return $lab === null
            ? __('app.pos.lens_form.out_of_range_any', ['combination' => $combination->label()])
            : __('app.pos.lens_form.out_of_range', ['combination' => $combination->label(), 'lab' => $lab->name]);
    }
}
