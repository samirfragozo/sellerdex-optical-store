<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('message_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('key');
            $table->text('body');
            $table->timestamps();

            $table->unique(['company_id', 'key']);
        });

        Schema::create('follow_up_contacts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->string('reason');
            $table->nullableMorphs('subject');
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('contacted_at');
            $table->timestamps();

            $table->index(['company_id', 'reason', 'contacted_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('follow_up_contacts');
        Schema::dropIfExists('message_templates');
    }
};
