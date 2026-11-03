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
        // A lab's price list: columns are lens type + technology (+ addition), rows are material + prescription range.
        Schema::create('lab_price_sheets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->index()->constrained()->cascadeOnDelete();
            $table->foreignId('supplier_id')->index()->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->timestamps();
        });

        Schema::create('lab_sheet_columns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->index()->constrained()->cascadeOnDelete();
            $table->foreignId('lab_price_sheet_id')->index()->constrained()->cascadeOnDelete();
            $table->string('label');
            $table->foreignId('lens_type_id')->index()->constrained()->cascadeOnDelete();
            $table->foreignId('lens_technology_id')->index()->constrained()->cascadeOnDelete();
            $table->decimal('add_min', 5, 2)->nullable();
            $table->decimal('add_max', 5, 2)->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('lab_sheet_rows', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->index()->constrained()->cascadeOnDelete();
            $table->foreignId('lab_price_sheet_id')->index()->constrained()->cascadeOnDelete();
            $table->string('label');
            $table->foreignId('lens_material_id')->index()->constrained()->cascadeOnDelete();
            $table->decimal('sphere_min', 5, 2);
            $table->decimal('sphere_max', 5, 2);
            $table->decimal('cylinder_min', 5, 2);
            $table->decimal('cylinder_max', 5, 2);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        // What the lab charges for one (row, column) pair, linked to the generic lens it prices.
        Schema::create('lab_sheet_cells', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->index()->constrained()->cascadeOnDelete();
            $table->foreignId('lab_price_sheet_id')->index()->constrained()->cascadeOnDelete();
            $table->foreignId('lab_sheet_row_id')->index()->constrained()->cascadeOnDelete();
            $table->foreignId('lab_sheet_column_id')->index()->constrained()->cascadeOnDelete();
            $table->foreignId('lens_combination_id')->index()->constrained()->cascadeOnDelete();
            $table->bigInteger('cost');
            $table->boolean('is_preferred')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['lab_sheet_row_id', 'lab_sheet_column_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('lab_sheet_cells');
        Schema::dropIfExists('lab_sheet_rows');
        Schema::dropIfExists('lab_sheet_columns');
        Schema::dropIfExists('lab_price_sheets');
    }
};
