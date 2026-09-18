<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lens_combinations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('lens_type_id')->constrained()->restrictOnDelete();
            $table->foreignId('lens_technology_id')->constrained()->restrictOnDelete();
            $table->foreignId('lens_material_id')->constrained()->restrictOnDelete();
            $table->bigInteger('cost')->default(0);
            $table->bigInteger('price')->default(0);
            $table->bigInteger('installation_price')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['company_id', 'lens_type_id', 'lens_technology_id', 'lens_material_id'], 'lens_combinations_unique_triple');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lens_combinations');
    }
};
