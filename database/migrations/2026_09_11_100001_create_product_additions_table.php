<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_additions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('addition_product_id')->constrained('products')->cascadeOnDelete();
            $table->bigInteger('price')->default(0);
            $table->unsignedInteger('quantity')->default(1);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['product_id', 'addition_product_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_additions');
    }
};
