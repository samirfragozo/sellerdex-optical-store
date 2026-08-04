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
        // business_settings predates multi-tenancy and was left as a single
        // global row shared by every company (companies already has the same
        // name/tax_id/address/phones/logo columns since the tenancy migration).
        Schema::dropIfExists('business_settings');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::create('business_settings', function (Blueprint $table) {
            $table->id();
            $table->string('name')->default('Mi Óptica');
            $table->string('tax_id')->nullable();
            $table->string('address')->nullable();
            $table->string('phones')->nullable();
            $table->string('logo')->nullable();
            $table->timestamps();
        });
    }
};
