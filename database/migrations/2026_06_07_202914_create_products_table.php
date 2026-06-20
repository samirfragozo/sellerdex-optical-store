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
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('sku')->nullable();
            $table->foreignId('product_category_id')->constrained();
            $table->string('brand')->nullable();
            $table->unsignedBigInteger('price')->default(0);
            $table->unsignedBigInteger('cost')->default(0);
            $table->boolean('is_stockable')->default(true);
            $table->integer('stock')->nullable();
            $table->boolean('is_active')->default(true);
            $table->json('specs')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
