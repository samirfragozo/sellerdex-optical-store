<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /** Map existing display names to stable keys for the backfill. */
    private const NAME_TO_KEY = [
        'Lente' => 'lens',
        'Montura' => 'frame',
        'Accesorio' => 'accessory',
        'Servicio' => 'service',
    ];

    public function up(): void
    {
        Schema::table('product_categories', function (Blueprint $table) {
            $table->string('key')->nullable()->after('name');
            $table->boolean('is_system')->default(false)->after('is_active');
            $table->boolean('requires_prescription')->default(false)->after('is_system');
            $table->boolean('generates_lab_order')->default(false)->after('requires_prescription');
            $table->boolean('is_made_to_order')->default(false)->after('generates_lab_order');
        });

        foreach (self::NAME_TO_KEY as $name => $key) {
            DB::table('product_categories')->where('name', $name)->update([
                'key' => $key,
                'is_system' => true,
                'requires_prescription' => $key === 'lens',
                'generates_lab_order' => $key === 'lens',
                'is_made_to_order' => $key === 'lens',
            ]);
        }

        // Backfill any remaining custom categories with a slug derived from the name.
        DB::table('product_categories')->whereNull('key')->get()->each(function ($row) {
            $base = Str::slug($row->name) ?: 'category';
            $key = $base;
            // Ensure uniqueness before the NOT NULL + UNIQUE change is applied.
            while (DB::table('product_categories')->where('key', $key)->whereKeyNot($row->id)->exists()) {
                $key = $base.'-'.$row->id;
            }
            DB::table('product_categories')->where('id', $row->id)->update(['key' => $key]);
        });

        Schema::table('product_categories', function (Blueprint $table) {
            $table->string('key')->nullable(false)->unique()->change();
        });
    }

    public function down(): void
    {
        Schema::table('product_categories', function (Blueprint $table) {
            $table->dropUnique(['key']);
            $table->dropColumn(['key', 'is_system', 'requires_prescription', 'generates_lab_order', 'is_made_to_order']);
        });
    }
};
