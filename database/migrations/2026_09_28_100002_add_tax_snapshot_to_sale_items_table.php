<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sale_items', function (Blueprint $table) {
            $table->string('tax_name')->nullable()->after('unit_cost');
            $table->decimal('tax_rate', 5, 2)->default(0)->after('tax_name');
            $table->string('tax_treatment')->nullable()->after('tax_rate');
        });
    }

    public function down(): void
    {
        Schema::table('sale_items', function (Blueprint $table) {
            $table->dropColumn(['tax_name', 'tax_rate', 'tax_treatment']);
        });
    }
};
