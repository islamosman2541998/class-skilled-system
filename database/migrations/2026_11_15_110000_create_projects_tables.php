<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('projects', function (Blueprint $table) {
            $table->id();
            $table->string('number', 30)->unique();
            $table->string('title_ar');
            $table->string('title_en')->nullable();
            $table->foreignId('client_id')->constrained();
            $table->foreignId('quotation_id')->nullable()->unique()->constrained()->nullOnDelete();
            $table->foreignId('service_id')->nullable()->constrained('lookups')->nullOnDelete();
            $table->foreignId('emirate_id')->nullable()->constrained('lookups')->nullOnDelete();
            $table->string('location')->nullable();
            $table->decimal('original_value', 14, 2)->default(0);
            $table->decimal('variations_total', 14, 2)->default(0);
            $table->decimal('contract_value', 14, 2)->default(0);
            $table->decimal('vat_rate', 5, 2);
            $table->string('billing_method', 20);
            $table->date('start_date')->nullable()->index();
            $table->date('planned_end_date')->nullable();
            $table->date('actual_end_date')->nullable();
            $table->foreignId('manager_id')->constrained('users');
            $table->string('status', 20);
            $table->decimal('progress', 5, 2)->default(0);
            $table->text('description')->nullable();
            // Summary columns kept up to date by Stage 3 (spec §11).
            $table->decimal('invoiced_total', 14, 2)->default(0);
            $table->decimal('collected_total', 14, 2)->default(0);
            $table->decimal('cost_total', 14, 2)->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'planned_end_date']);
            $table->index('manager_id');
            $table->index('contract_value');
        });

        Schema::create('project_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['project_id', 'user_id']);
            $table->index('user_id');
        });

        Schema::create('project_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('section_ar')->nullable();
            $table->string('section_en')->nullable();
            $table->text('description_ar');
            $table->text('description_en')->nullable();
            $table->foreignId('unit_id')->nullable()->constrained('lookups')->nullOnDelete();
            $table->decimal('quantity', 12, 3);
            $table->decimal('unit_price', 14, 2);
            $table->decimal('line_total', 14, 2);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_items');
        Schema::dropIfExists('project_members');
        Schema::dropIfExists('projects');
    }
};
