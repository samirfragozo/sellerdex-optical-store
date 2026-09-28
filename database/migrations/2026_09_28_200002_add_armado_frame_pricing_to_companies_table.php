<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->string('armado_frame_price_mode')->default('included');
            $table->decimal('armado_frame_discount_percent', 5, 2)->default(0);
        });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropColumn(['armado_frame_price_mode', 'armado_frame_discount_percent']);
        });
    }
};
