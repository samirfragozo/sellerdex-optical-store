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
        Schema::create('prescriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('sale_id')->nullable(); // FK added in a later plan (sales table not yet created)
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->date('exam_date');
            // OD (right eye)
            $table->string('od_sphere')->nullable();
            $table->string('od_cylinder')->nullable();
            $table->string('od_axis')->nullable();
            $table->string('od_add')->nullable();
            $table->string('od_va')->nullable();
            $table->string('od_pd')->nullable();
            // OS (left eye)
            $table->string('os_sphere')->nullable();
            $table->string('os_cylinder')->nullable();
            $table->string('os_axis')->nullable();
            $table->string('os_add')->nullable();
            $table->string('os_va')->nullable();
            $table->string('os_pd')->nullable();
            $table->string('lens_type')->nullable();
            $table->json('filters')->nullable();
            $table->string('usage')->nullable();
            $table->string('control_period')->nullable();
            $table->text('diagnosis')->nullable();
            $table->string('drops')->nullable();
            $table->string('lensometry')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('prescriptions');
    }
};
