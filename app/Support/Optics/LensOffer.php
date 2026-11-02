<?php

namespace App\Support\Optics;

use App\Models\LensCombinationPrice;
use App\Models\Supplier;

/**
 * One lab's offer for a pair of lenses: each eye costed by its own
 * narrowest covering row, combined into the pair's cost; the price is
 * the lens's band price, the same at every lab.
 */
final readonly class LensOffer
{
    public function __construct(
        public Supplier $supplier,
        public LensCombinationPrice $odRow,
        public LensCombinationPrice $osRow,
        public int $cost,
        public int $price,
        public bool $isPreferred,
    ) {}
}
