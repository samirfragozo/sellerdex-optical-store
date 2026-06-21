<?php

use App\Enums\CashCloseStatus;
use App\Enums\CashCloseType;
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
        Schema::create('cash_closes', function (Blueprint $table) {
            $table->id();
            $table->string('type')->default(CashCloseType::Daily->value);
            $table->date('period_start');
            $table->date('period_end');
            $table->unsignedBigInteger('opening_cash')->default(0);
            $table->unsignedBigInteger('total_sales')->default(0);
            $table->unsignedBigInteger('total_collected')->default(0);
            $table->json('collected_by_method')->nullable();
            $table->unsignedBigInteger('total_expenses')->default(0);
            $table->unsignedBigInteger('total_receivable')->default(0);
            $table->unsignedBigInteger('expected_cash')->default(0);
            $table->unsignedBigInteger('counted_cash')->default(0);
            $table->bigInteger('difference')->default(0);
            $table->string('status')->default(CashCloseStatus::Open->value);
            $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('closed_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cash_closes');
    }
};
