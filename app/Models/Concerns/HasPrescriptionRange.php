<?php

namespace App\Models\Concerns;

use App\Models\Prescription;
use Illuminate\Support\Collection;

/**
 * A model carrying a prescription range: sphere, cylinder (minus) and an
 * optionally open addition.
 */
trait HasPrescriptionRange
{
    /**
     * The narrowest of $ranges (ordered by id, so ties go to the oldest) covering one eye.
     *
     * @param  Collection<int, static>  $ranges
     * @param  array{sphere: float, cylinder: float}  $eye
     */
    public static function narrowestCovering(Collection $ranges, array $eye, float $add): ?static
    {
        return $ranges
            ->filter(fn (self $range): bool => $range->covers($eye['sphere'], $eye['cylinder'], $add))
            ->sortBy(fn (self $range): float => $range->rangeWidth())
            ->first();
    }

    /** Whether this range covers one eye (minus cylinder) and the addition; a null addition bound is open. */
    public function covers(float $sphere, float $cylinder, float $add): bool
    {
        return (float) $this->sphere_min <= $sphere && $sphere <= (float) $this->sphere_max
            && (float) $this->cylinder_min <= $cylinder && $cylinder <= (float) $this->cylinder_max
            && ($this->add_min === null || (float) $this->add_min <= $add)
            && ($this->add_max === null || $add <= (float) $this->add_max);
    }

    /** How broad this range is, so a specific high-power range beats "all prescriptions". */
    public function rangeWidth(): float
    {
        return ((float) $this->sphere_max - (float) $this->sphere_min)
            + ((float) $this->cylinder_max - (float) $this->cylinder_min)
            + ((float) ($this->add_max ?? Prescription::LIMITS['add'][1]) - (float) ($this->add_min ?? 0));
    }
}
