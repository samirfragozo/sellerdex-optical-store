<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Sale prices moved to lens_price_bands; lab rows only need their cost now.
        Schema::table('lens_combination_prices', function (Blueprint $table) {
            $table->bigInteger('price')->default(0)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('lens_combination_prices', function (Blueprint $table) {
            $table->bigInteger('price')->change();
        });
    }
};
