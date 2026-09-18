<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sale_item_lens_configs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sale_item_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('lens_combination_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type_name');
            $table->string('technology_name');
            $table->string('material_name');
            $table->bigInteger('combination_cost');
            $table->bigInteger('combination_price');
            $table->bigInteger('installation_price');
            $table->foreignId('lens_package_id')->nullable()->constrained()->nullOnDelete();
            $table->string('package_name');
            $table->bigInteger('package_price');
            $table->bigInteger('package_cost');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sale_item_lens_configs');
    }
};
