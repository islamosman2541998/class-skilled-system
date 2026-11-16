<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Workers and their daily attendance on projects (spec §8.17). */
    public function up(): void
    {
        Schema::create('workers', function (Blueprint $table) {
            $table->id();
            $table->string('name_ar', 190);
            $table->string('name_en', 190)->nullable();
            $table->foreignId('trade_id')->nullable()->constrained('lookups')->nullOnDelete();
            $table->decimal('daily_wage', 10, 2);
            $table->boolean('is_company')->default(true);
            $table->string('phone', 30)->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('attendances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->restrictOnDelete();
            $table->foreignId('worker_id')->constrained()->restrictOnDelete();
            $table->date('work_date');
            $table->string('status', 20);
            $table->decimal('overtime_hours', 5, 2)->default(0);
            $table->decimal('daily_wage', 10, 2);
            $table->decimal('cost', 14, 2);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['worker_id', 'work_date']);
            $table->index(['project_id', 'work_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendances');
        Schema::dropIfExists('workers');
    }
};
