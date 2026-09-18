<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sale_item_lens_treatments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sale_item_lens_config_id')->constrained()->cascadeOnDelete();
            $table->foreignId('lens_treatment_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->bigInteger('price');
            $table->bigInteger('cost');
            $table->timestamps();

            $table->index('sale_item_lens_config_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sale_item_lens_treatments');
    }
};
