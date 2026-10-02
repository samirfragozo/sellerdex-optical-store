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
        // Each armado is for its own patient, on its own prescription; the sale's
        // customer only pays. nullOnDelete: purging a patient or a prescription
        // (health data) must never delete the sale.
        Schema::table('sale_item_lens_configs', function (Blueprint $table) {
            $table->foreignId('patient_id')->nullable()->after('sale_item_id')->constrained('customers')->nullOnDelete();
            $table->foreignId('prescription_id')->nullable()->after('patient_id')->constrained()->nullOnDelete();
        });

        Schema::table('sales', function (Blueprint $table) {
            $table->dropConstrainedForeignId('prescription_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->foreignId('prescription_id')->nullable()->after('seller_id')->constrained()->nullOnDelete();
        });

        Schema::table('sale_item_lens_configs', function (Blueprint $table) {
            $table->dropConstrainedForeignId('prescription_id');
            $table->dropConstrainedForeignId('patient_id');
        });
    }
};
