<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kit_slots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('trigger');
            $table->foreignId('trigger_category_id')->nullable()->constrained('product_categories')->restrictOnDelete();
            $table->foreignId('trigger_product_id')->nullable()->constrained('products')->cascadeOnDelete();
            $table->string('scope_key');
            $table->foreignId('slot_category_id')->constrained('product_categories')->restrictOnDelete();
            $table->foreignId('default_product_id')->constrained('products')->restrictOnDelete();
            $table->unsignedSmallInteger('quantity')->default(1);
            $table->string('price_mode');
            $table->decimal('price_value', 12, 2)->default(0);
            $table->boolean('is_optional')->default(false);
            $table->boolean('is_preselected')->default(true);
            $table->foreignId('upgrade_product_id')->nullable()->constrained('products')->nullOnDelete();
            $table->unsignedInteger('upgrade_min_total')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            // One product per category per combo (nullable trigger ids cannot back a unique index).
            $table->unique(['company_id', 'scope_key', 'slot_category_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kit_slots');
    }
};
