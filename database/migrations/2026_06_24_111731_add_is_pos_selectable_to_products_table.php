<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Products that are auto-included by combos and never sold on their own.
     */
    private const NON_SELLABLE_SKUS = [
        'ACC-BOLSA-PAPEL',
        'ACC-BOLSA-PLASTICO',
        'ACC-FUNDA',
        'ACC-PANO',
    ];

    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->boolean('is_pos_selectable')->default(true)->after('is_active');
        });

        DB::table('products')
            ->whereIn('sku', self::NON_SELLABLE_SKUS)
            ->update(['is_pos_selectable' => false]);
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('is_pos_selectable');
        });
    }
};
