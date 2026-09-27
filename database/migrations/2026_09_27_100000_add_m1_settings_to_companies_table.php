<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->renameColumn('lens_onboarding_completed_at', 'onboarded_at');
        });

        Schema::table('companies', function (Blueprint $table) {
            $table->string('vat_regime')->default('responsible')->after('tax_id');
            $table->string('sale_number_prefix', 10)->nullable()->after('vat_regime');
            $table->unsignedInteger('next_sale_number')->default(1)->after('sale_number_prefix');
            $table->string('onboarding_step')->nullable()->after('plan');
        });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropColumn(['vat_regime', 'sale_number_prefix', 'next_sale_number', 'onboarding_step']);
        });

        Schema::table('companies', function (Blueprint $table) {
            $table->renameColumn('onboarded_at', 'lens_onboarding_completed_at');
        });
    }
};
