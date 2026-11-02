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
        // What the shop charges for a lens per prescription range, independent of the lab.
        Schema::create('lens_price_bands', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('lens_combination_id')->constrained()->cascadeOnDelete();
            $table->decimal('sphere_min', 5, 2);
            $table->decimal('sphere_max', 5, 2);
            $table->decimal('cylinder_min', 5, 2);
            $table->decimal('cylinder_max', 5, 2);
            $table->decimal('add_min', 5, 2)->nullable();
            $table->decimal('add_max', 5, 2)->nullable();
            $table->bigInteger('price');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('lens_price_bands');
    }
};
