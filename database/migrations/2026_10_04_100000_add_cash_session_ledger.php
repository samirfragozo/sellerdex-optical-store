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
        Schema::table('cash_register_sessions', function (Blueprint $table) {
            $table->unsignedBigInteger('cash_left')->nullable();
            $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('closed_by_admin')->default(false);
            $table->timestamp('reviewed_at')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->foreignId('cash_register_session_id')->nullable()->constrained()->nullOnDelete();
        });

        Schema::table('expenses', function (Blueprint $table) {
            $table->foreignId('cash_register_session_id')->nullable()->constrained()->nullOnDelete();
        });

        Schema::table('companies', function (Blueprint $table) {
            $table->boolean('blind_cash_count')->default(false);
            $table->unsignedInteger('cash_difference_note_threshold')->default(0);
        });

        Schema::create('cash_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('cash_register_session_id')->constrained()->cascadeOnDelete();
            $table->string('type');
            $table->unsignedBigInteger('amount');
            $table->string('reason');
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('cash_register_session_counts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('cash_register_session_id')->constrained()->cascadeOnDelete();
            $table->foreignId('payment_method_id')->constrained()->restrictOnDelete();
            $table->bigInteger('expected');
            $table->bigInteger('counted');
            $table->bigInteger('difference');
            $table->timestamps();

            $table->unique(['cash_register_session_id', 'payment_method_id'], 'cash_session_counts_session_method_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cash_register_session_counts');
        Schema::dropIfExists('cash_movements');

        Schema::table('companies', function (Blueprint $table) {
            $table->dropColumn(['blind_cash_count', 'cash_difference_note_threshold']);
        });

        Schema::table('expenses', function (Blueprint $table) {
            $table->dropConstrainedForeignId('cash_register_session_id');
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('cash_register_session_id');
        });

        Schema::table('cash_register_sessions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('closed_by');
            $table->dropConstrainedForeignId('reviewed_by');
            $table->dropColumn(['cash_left', 'closed_by_admin', 'reviewed_at']);
        });
    }
};
