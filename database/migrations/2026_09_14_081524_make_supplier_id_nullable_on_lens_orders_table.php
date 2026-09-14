<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lens_orders', function (Blueprint $table) {
            $table->dropForeign(['supplier_id']);
        });

        Schema::table('lens_orders', function (Blueprint $table) {
            $table->foreignId('supplier_id')->nullable()->change();
            $table->foreign('supplier_id')->references('id')->on('suppliers');
        });
    }

    public function down(): void
    {
        Schema::table('lens_orders', function (Blueprint $table) {
            $table->dropForeign(['supplier_id']);
        });

        Schema::table('lens_orders', function (Blueprint $table) {
            $table->foreignId('supplier_id')->nullable(false)->change();
            $table->foreign('supplier_id')->references('id')->on('suppliers');
        });
    }
};
