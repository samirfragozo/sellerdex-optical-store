<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('warranty_claims', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sale_item_id')->constrained()->restrictOnDelete();
            $table->string('type');
            $table->text('customer_description');
            $table->date('received_at');
            $table->string('status')->default('received');
            $table->string('resolution')->nullable();
            $table->string('rejection_reason')->nullable();
            $table->string('responsible')->nullable();
            $table->unsignedInteger('store_cost')->default(0);
            $table->foreignId('replacement_product_id')->nullable()->constrained('products')->nullOnDelete();
            $table->foreignId('lens_order_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('sale_return_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();
            $table->date('delivered_at')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('warranty_claims');
    }
};
