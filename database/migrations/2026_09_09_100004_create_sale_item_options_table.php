<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sale_item_options', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sale_item_id')->constrained()->cascadeOnDelete();
            $table->foreignId('option_group_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('option_id')->nullable()->constrained()->nullOnDelete();
            $table->string('option_group_name');
            $table->string('option_name');
            $table->bigInteger('price');
            $table->bigInteger('cost');
            $table->timestamps();

            $table->index(['sale_item_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sale_item_options');
    }
};
