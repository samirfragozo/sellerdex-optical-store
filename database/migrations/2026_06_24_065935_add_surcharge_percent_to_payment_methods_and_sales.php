<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payment_methods', function (Blueprint $table) {
            $table->decimal('surcharge_percent', 5, 2)->default(0)->after('is_default');
        });
        Schema::table('sales', function (Blueprint $table) {
            $table->decimal('surcharge_percent', 5, 2)->default(0)->after('discount');
        });
    }

    public function down(): void
    {
        Schema::table('payment_methods', fn (Blueprint $t) => $t->dropColumn('surcharge_percent'));
        Schema::table('sales', fn (Blueprint $t) => $t->dropColumn('surcharge_percent'));
    }
};
