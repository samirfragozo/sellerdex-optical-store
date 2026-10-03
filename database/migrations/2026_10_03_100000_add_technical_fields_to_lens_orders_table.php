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
        Schema::table('lens_orders', function (Blueprint $table) {
            // A remake is a second order on the same sale item.
            $table->dropUnique(['sale_item_id']);
            $table->index('sale_item_id');

            $table->decimal('od_pd', 4, 1)->nullable();
            $table->decimal('os_pd', 4, 1)->nullable();
            $table->decimal('od_height', 4, 1)->nullable();
            $table->decimal('os_height', 4, 1)->nullable();
            $table->decimal('frame_a', 4, 1)->nullable();
            $table->decimal('frame_b', 4, 1)->nullable();
            $table->decimal('frame_dbl', 4, 1)->nullable();
            $table->string('frame_type')->nullable();
            $table->string('frame_source')->default('sold');
            $table->string('customer_frame_description')->nullable();
            $table->string('customer_frame_condition')->nullable();
            $table->json('prescription_snapshot')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('customer_notified_at')->nullable();
            $table->foreignId('remake_of_id')->nullable()->constrained('lens_orders')->nullOnDelete();
            $table->string('remake_reason')->nullable();
            $table->string('remake_responsible')->nullable();
            $table->bigInteger('remake_cost')->default(0);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('lens_orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('remake_of_id');
            $table->dropColumn([
                'od_pd', 'os_pd', 'od_height', 'os_height', 'frame_a', 'frame_b', 'frame_dbl',
                'frame_type', 'frame_source', 'customer_frame_description', 'customer_frame_condition',
                'prescription_snapshot', 'sent_at', 'customer_notified_at',
                'remake_reason', 'remake_responsible', 'remake_cost',
            ]);
            $table->dropIndex(['sale_item_id']);
            $table->unique('sale_item_id');
        });
    }
};
