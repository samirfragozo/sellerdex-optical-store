<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->unsignedSmallInteger('quote_validity_days')->default(15);
            $table->decimal('seller_max_discount_percent', 5, 2)->default(0);
            $table->unsignedSmallInteger('adaptation_warranty_days')->default(30);
        });

        Schema::table('product_categories', function (Blueprint $table) {
            $table->unsignedTinyInteger('warranty_months')->default(12);
        });

        Schema::table('sale_items', function (Blueprint $table) {
            $table->unsignedTinyInteger('warranty_months')->nullable();
        });

        Schema::table('sales', function (Blueprint $table) {
            $table->date('quote_valid_until')->nullable();
            $table->foreignId('discount_approved_by')->nullable()->constrained('users')->nullOnDelete();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->string('approval_pin')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('approval_pin'));
        Schema::table('sales', function (Blueprint $table) {
            $table->dropConstrainedForeignId('discount_approved_by');
            $table->dropColumn('quote_valid_until');
        });
        Schema::table('sale_items', fn (Blueprint $table) => $table->dropColumn('warranty_months'));
        Schema::table('product_categories', fn (Blueprint $table) => $table->dropColumn('warranty_months'));
        Schema::table('companies', fn (Blueprint $table) => $table->dropColumn(['quote_validity_days', 'seller_max_discount_percent', 'adaptation_warranty_days']));
    }
};
