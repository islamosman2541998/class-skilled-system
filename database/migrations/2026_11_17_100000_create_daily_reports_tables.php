<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Daily site reports (spec §8.13): one per project per day, with the stages worked on. */
    public function up(): void
    {
        Schema::create('daily_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->date('report_date');
            $table->foreignId('engineer_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('work_done');
            $table->unsignedSmallInteger('labour_count')->default(0);
            $table->text('obstacles')->nullable();
            $table->boolean('has_obstacles')->default(false)->index();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['project_id', 'report_date']);
            $table->index('report_date');
        });

        Schema::create('daily_report_stages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('daily_report_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_stage_id')->constrained()->cascadeOnDelete();
            $table->decimal('progress', 5, 2);
            $table->timestamps();

            $table->unique(['daily_report_id', 'project_stage_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('daily_report_stages');
        Schema::dropIfExists('daily_reports');
    }
};
