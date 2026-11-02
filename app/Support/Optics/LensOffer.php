<?php

namespace App\Support\Optics;

use App\Models\LensCombinationPrice;
use App\Models\Supplier;

/**
 * One lab's price for a pair of lenses: each eye priced by its own
 * narrowest covering row, combined into the pair's cost and price.
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
