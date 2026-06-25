<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Prefix every lens product name with the word "Lente".
     */
    public function up(): void
    {
        $lenteId = DB::table('product_categories')->where('name', 'Lente')->value('id');

        if ($lenteId === null) {
            return;
        }

        DB::table('products')
            ->where('product_category_id', $lenteId)
            ->where('name', 'not like', 'Lente %')
            ->orderBy('id')
            ->chunkById(100, function ($products): void {
                foreach ($products as $product) {
                    DB::table('products')
                        ->where('id', $product->id)
                        ->update(['name' => 'Lente '.$product->name]);
                }
            });
    }

    /**
     * Remove the "Lente" prefix from lens product names.
     */
    public function down(): void
    {
        $lenteId = DB::table('product_categories')->where('name', 'Lente')->value('id');

        if ($lenteId === null) {
            return;
        }

        DB::table('products')
            ->where('product_category_id', $lenteId)
            ->where('name', 'like', 'Lente %')
            ->orderBy('id')
            ->chunkById(100, function ($products): void {
                foreach ($products as $product) {
                    DB::table('products')
                        ->where('id', $product->id)
                        ->update(['name' => substr($product->name, strlen('Lente '))]);
                }
            });
    }
};
