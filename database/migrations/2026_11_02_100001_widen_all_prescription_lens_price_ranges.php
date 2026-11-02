<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * "All prescriptions" used to mean sphere -20..20 / cylinder -10..10; the real
 * manufacturing limits are sphere -25..20 / cylinder -8..8. Only rows that held
 * exactly the old whole-scale values move; custom ranges stay as they are.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('lens_combination_prices')
            ->where(['sphere_min' => -20, 'sphere_max' => 20, 'cylinder_min' => -10, 'cylinder_max' => 10])
            ->update(['sphere_min' => -25, 'sphere_max' => 20, 'cylinder_min' => -8, 'cylinder_max' => 8]);
    }

    public function down(): void
    {
        DB::table('lens_combination_prices')
            ->where(['sphere_min' => -25, 'sphere_max' => 20, 'cylinder_min' => -8, 'cylinder_max' => 8])
            ->update(['sphere_min' => -20, 'sphere_max' => 20, 'cylinder_min' => -10, 'cylinder_max' => 10]);
    }
};
