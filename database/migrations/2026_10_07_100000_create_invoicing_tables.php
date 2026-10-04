<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->string('invoicing_mode')->default('undecided');
            $table->string('default_fiscal_document')->default('pos_electronic');
            $table->date('invoice_resolution_expires_at')->nullable();
            $table->date('pos_resolution_expires_at')->nullable();
            $table->string('layaway_invoicing')->default('on_delivery');
        });

        Schema::create('numbering_ranges', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('document_type');
            $table->string('prefix', 10)->nullable();
            $table->unsignedInteger('next_number')->default(1);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            // ponytail: one range per type; a future store_id joins this index.
            $table->unique(['company_id', 'document_type']);
        });

        Schema::create('fiscal_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sale_id')->constrained()->restrictOnDelete();
            $table->foreignId('sale_return_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('document_type');
            $table->string('source');
            $table->string('number', 50);
            $table->date('issued_at');
            $table->string('status');
            $table->string('cufe_or_cude', 120)->nullable();
            $table->string('pdf_path')->nullable();
            $table->foreignId('reference_fiscal_document_id')->nullable()->constrained('fiscal_documents')->restrictOnDelete();
            $table->foreignId('registered_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['company_id', 'document_type', 'number']);
            $table->index(['company_id', 'issued_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fiscal_documents');
        Schema::dropIfExists('numbering_ranges');
        Schema::table('companies', fn (Blueprint $table) => $table->dropColumn([
            'invoicing_mode', 'default_fiscal_document', 'invoice_resolution_expires_at', 'pos_resolution_expires_at', 'layaway_invoicing',
        ]));
    }
};
