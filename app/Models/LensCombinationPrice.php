<?php

namespace App\Models;

use App\Models\Concerns\HasPrescriptionRange;
use App\Traits\BelongsToCompany;
use Database\Factories\LensCombinationPriceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @deprecated Legacy per-lab rows, no longer read; replaced by lab sheet cells.
 */
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
}
