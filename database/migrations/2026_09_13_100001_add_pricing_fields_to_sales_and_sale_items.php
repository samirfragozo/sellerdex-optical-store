<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales', function (Blueprint $table): void {
            $table->decimal('discount_percent', 5, 2)->default(0)->after('discount');
            $table->decimal('tip_percent', 5, 2)->default(0)->after('surcharge_percent');
            $table->integer('tip')->default(0)->after('tip_percent');
            $table->integer('tax_amount')->default(0)->after('subtotal');
        });

        Schema::table('sale_items', function (Blueprint $table): void {
            $table->integer('tax_amount')->default(0)->after('unit_cost');
        });
    }

    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table): void {
            $table->dropColumn(['discount_percent', 'tip_percent', 'tip', 'tax_amount']);
        });
        Schema::table('sale_items', function (Blueprint $table): void {
            $table->dropColumn('tax_amount');
        });
    }
};
