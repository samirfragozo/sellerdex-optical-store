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
        // A lens is priced per lab and per prescription range; the combination
        // keeps only what doesn't vary with them (installation, tax, active).
        Schema::create('lens_combination_prices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('lens_combination_id')->constrained()->cascadeOnDelete();
            $table->foreignId('supplier_id')->constrained()->cascadeOnDelete();
            $table->decimal('sphere_min', 5, 2);
            $table->decimal('sphere_max', 5, 2);
            $table->decimal('cylinder_min', 5, 2);
            $table->decimal('cylinder_max', 5, 2);
            $table->decimal('add_min', 5, 2)->nullable();
            $table->decimal('add_max', 5, 2)->nullable();
            $table->bigInteger('cost')->default(0);
            $table->bigInteger('price');
            $table->boolean('is_preferred')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['lens_combination_id', 'supplier_id']);
        });

        Schema::table('lens_combinations', function (Blueprint $table) {
            $table->dropColumn(['cost', 'price']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('lens_combinations', function (Blueprint $table) {
            $table->bigInteger('cost')->default(0)->after('lens_material_id');
            $table->bigInteger('price')->default(0)->after('cost');
        });

        Schema::dropIfExists('lens_combination_prices');
    }
};
