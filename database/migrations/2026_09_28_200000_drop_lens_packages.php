<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sale_item_lens_configs', function (Blueprint $table) {
            $table->dropConstrainedForeignId('lens_package_id');
            $table->dropColumn(['package_name', 'package_price', 'package_cost']);
        });
        Schema::dropIfExists('lens_packages');
    }

    public function down(): void
    {
        Schema::create('lens_packages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->bigInteger('price')->default(0);
            $table->bigInteger('cost')->default(0);
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
        Schema::table('sale_item_lens_configs', function (Blueprint $table) {
            $table->foreignId('lens_package_id')->nullable()->constrained('lens_packages')->nullOnDelete();
            $table->string('package_name')->nullable();
            $table->bigInteger('package_price')->default(0);
            $table->bigInteger('package_cost')->default(0);
        });
    }
};
