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
        Schema::table('prescriptions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('sale_id');
            $table->dropColumn('lens_type');
        });

        Schema::table('prescriptions', function (Blueprint $table) {
            foreach (['od', 'os'] as $eye) {
                $table->decimal("{$eye}_sphere", 5, 2)->nullable()->change();
                $table->decimal("{$eye}_cylinder", 5, 2)->nullable()->change();
                $table->unsignedSmallInteger("{$eye}_axis")->nullable()->change();
                $table->decimal("{$eye}_add", 5, 2)->nullable()->change();
                $table->decimal("{$eye}_pd", 4, 1)->nullable()->change();
            }

            $table->decimal('od_prism', 4, 2)->nullable()->after('os_pd');
            $table->string('od_prism_base')->nullable()->after('od_prism');
            $table->decimal('os_prism', 4, 2)->nullable()->after('od_prism_base');
            $table->string('os_prism_base')->nullable()->after('os_prism');
            $table->string('prescriber_name')->nullable()->after('os_prism_base');
            $table->string('prescriber_license')->nullable()->after('prescriber_name');
            $table->string('attachment')->nullable()->after('prescriber_license');
            $table->date('expires_at')->nullable()->after('exam_date');
            $table->text('notes')->nullable()->after('diagnosis');
        });

        Schema::table('companies', function (Blueprint $table) {
            $table->unsignedTinyInteger('prescription_validity_months')->default(12)->after('sale_number_prefix');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropColumn('prescription_validity_months');
        });

        Schema::table('prescriptions', function (Blueprint $table) {
            $table->dropColumn([
                'od_prism', 'od_prism_base', 'os_prism', 'os_prism_base',
                'prescriber_name', 'prescriber_license', 'attachment', 'expires_at', 'notes',
            ]);

            foreach (['od', 'os'] as $eye) {
                foreach (['sphere', 'cylinder', 'axis', 'add', 'pd'] as $field) {
                    $table->string("{$eye}_{$field}")->nullable()->change();
                }
            }
        });

        Schema::table('prescriptions', function (Blueprint $table) {
            $table->foreignId('sale_id')->nullable()->after('customer_id')->constrained()->nullOnDelete();
            $table->string('lens_type')->nullable()->after('os_pd');
        });
    }
};
