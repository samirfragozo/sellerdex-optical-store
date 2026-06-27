<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lens_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sale_item_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('supplier_id')->constrained();
            $table->string('lab_status')->default('sent');
            $table->date('expected_date')->nullable();
            $table->date('received_date')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lens_orders');
    }
};
