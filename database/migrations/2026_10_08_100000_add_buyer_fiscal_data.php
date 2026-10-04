<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->string('person_type')->nullable();
            $table->string('dane_municipality_code', 5)->nullable();
            $table->json('fiscal_responsibilities')->nullable();
        });

        Schema::table('sales', function (Blueprint $table) {
            $table->string('fiscal_document_type')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('sales', fn (Blueprint $table) => $table->dropColumn('fiscal_document_type'));
        Schema::table('customers', fn (Blueprint $table) => $table->dropColumn(['person_type', 'dane_municipality_code', 'fiscal_responsibilities']));
    }
};
