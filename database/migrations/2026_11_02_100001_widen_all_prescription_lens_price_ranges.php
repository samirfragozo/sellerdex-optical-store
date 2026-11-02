<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Prescription ranges used to stop at sphere -20 and cylinder -10..10; the real
 * manufacturing limits are sphere -25..20 / cylinder -8..8. Each old bound moves
 * on its own, so a custom range like "sphere -20..-6.25" keeps its other edge.
 */
return new class extends Migration
{
    public function up(): void
    {
        $prices = DB::table('lens_combination_prices');

        (clone $prices)->where('sphere_min', -20)->update(['sphere_min' => -25]);
        (clone $prices)->where('cylinder_min', -10)->update(['cylinder_min' => -8]);
        (clone $prices)->where('cylinder_max', 10)->update(['cylinder_max' => 8]);
    }

    public function down(): void
    {
        $prices = DB::table('lens_combination_prices');

        (clone $prices)->where('sphere_min', -25)->update(['sphere_min' => -20]);
        (clone $prices)->where('cylinder_min', -8)->update(['cylinder_min' => -10]);
        (clone $prices)->where('cylinder_max', 8)->update(['cylinder_max' => 10]);
    }
};
