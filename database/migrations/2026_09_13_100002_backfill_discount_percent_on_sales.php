<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Backfill discount_percent for sales created before it existed. Those
     * rows have a real peso amount in `discount` but `discount_percent` at
     * its default of 0, which would silently zero their discount the next
     * time Sale::recalculateTotals() runs (it derives discount from
     * discount_percent, not the other way around).
     */
    public function up(): void
    {
        DB::table('sales')
            ->where('discount', '>', 0)
            ->where('subtotal', '>', 0)
            ->update([
                'discount_percent' => DB::raw('ROUND(discount * 100.0 / subtotal, 2)'),
            ]);
    }

    public function down(): void
    {
        // ponytail: a backfill isn't meaningfully reversible — we'd have no
        // way to distinguish rows this migration touched from rows whose
        // discount_percent was set some other way.
    }
};
