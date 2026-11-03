<?php

namespace App\Support\Optics;

use App\Models\LabSheetCell;
use App\Models\Supplier;

/**
 * One lab's offer for a pair of lenses: each eye costed by its own
 * narrowest covering sheet cell, combined into the pair's cost; the price
 * is the lens's band price, the same at every lab.
 */
final readonly class LensOffer
{
    public function __construct(
        public Supplier $supplier,
        public LabSheetCell $odCell,
        public LabSheetCell $osCell,
        public int $cost,
        public int $price,
        public bool $isPreferred,
    ) {}
}
