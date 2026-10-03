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
        Schema::create('sale_returns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sale_id')->constrained()->cascadeOnDelete();
            $table->string('type');
            $table->string('reason');
            $table->string('money_destination');
            $table->bigInteger('total');
            $table->bigInteger('refund_amount')->default(0);
            $table->foreignId('refund_payment_method_id')->nullable()->constrained('payment_methods')->nullOnDelete();
            $table->bigInteger('store_credit_amount')->default(0);
            $table->bigInteger('retained_amount')->default(0);
            $table->bigInteger('loss_amount')->default(0);
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('sale_return_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sale_return_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sale_item_id')->constrained()->restrictOnDelete();
            $table->integer('quantity');
            $table->bigInteger('amount');
            $table->boolean('restock')->default(false);
            $table->timestamps();
        });

        Schema::create('customer_credits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->bigInteger('amount');
            $table->nullableMorphs('source');
            $table->timestamps();

            $table->index(['customer_id', 'id']);
        });

        Schema::table('payment_methods', function (Blueprint $table) {
            $table->boolean('is_store_credit')->default(false);
        });

        Schema::table('companies', function (Blueprint $table) {
            $table->decimal('layaway_cancellation_fee_percent', 5, 2)->default(0);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropColumn('layaway_cancellation_fee_percent');
        });

        Schema::table('payment_methods', function (Blueprint $table) {
            $table->dropColumn('is_store_credit');
        });

        Schema::dropIfExists('customer_credits');
        Schema::dropIfExists('sale_return_items');
        Schema::dropIfExists('sale_returns');
    }
};
