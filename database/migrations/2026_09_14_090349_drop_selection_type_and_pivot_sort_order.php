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
        Schema::table('option_groups', function (Blueprint $table) {
            $table->dropColumn('selection_type');
        });

        Schema::table('product_option_groups', function (Blueprint $table) {
            $table->dropColumn('sort_order');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('option_groups', function (Blueprint $table) {
            $table->string('selection_type')->default('single');
        });

        Schema::table('product_option_groups', function (Blueprint $table) {
            $table->unsignedInteger('sort_order')->nullable();
        });
    }
};
