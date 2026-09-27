<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('tax_rate');
        });
        Schema::table('products', function (Blueprint $table) {
            $table->foreignId('tax_id')->nullable()->after('cost')->constrained('taxes')->restrictOnDelete();
        });
        Schema::table('product_categories', function (Blueprint $table) {
            $table->foreignId('default_tax_id')->nullable()->after('is_made_to_order')->constrained('taxes')->restrictOnDelete();
        });
        Schema::table('lens_combinations', function (Blueprint $table) {
            $table->foreignId('tax_id')->nullable()->after('installation_price')->constrained('taxes')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        foreach (['products' => 'tax_id', 'product_categories' => 'default_tax_id', 'lens_combinations' => 'tax_id'] as $table => $column) {
            Schema::table($table, function (Blueprint $t) use ($column) {
                $t->dropConstrainedForeignId($column);
            });
        }
        Schema::table('products', function (Blueprint $table) {
            $table->decimal('tax_rate', 5, 2)->default(0)->after('cost');
        });
    }
};
