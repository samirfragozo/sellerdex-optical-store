<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A lab may deliver in a range (3 to 5 days): lead_time_days is the shortest,
     * lead_time_max_days the longest. A lens order keeps expected_date as the
     * latest arrival and expected_date_from as the earliest, only for ranges.
     */
    public function up(): void
    {
        Schema::table('suppliers', function (Blueprint $table) {
            $table->unsignedSmallInteger('lead_time_max_days')->nullable()->after('lead_time_days');
        });

        Schema::table('lens_orders', function (Blueprint $table) {
            $table->date('expected_date_from')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('suppliers', function (Blueprint $table) {
            $table->dropColumn('lead_time_max_days');
        });

        Schema::table('lens_orders', function (Blueprint $table) {
            $table->dropColumn('expected_date_from');
        });
    }
};
