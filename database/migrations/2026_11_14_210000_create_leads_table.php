<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leads', function (Blueprint $table) {
            $table->id();
            $table->string('number', 30)->unique();
            $table->foreignId('client_id')->nullable()->constrained()->nullOnDelete();
            $table->string('contact_name')->nullable();
            $table->string('contact_phone', 40)->nullable();
            $table->string('contact_phone_normalized', 20)->nullable()->index();
            $table->string('contact_email')->nullable();
            $table->foreignId('service_id')->nullable()->constrained('lookups')->nullOnDelete();
            $table->string('facility_type', 100)->nullable();
            $table->string('project_state', 100)->nullable();
            $table->foreignId('emirate_id')->nullable()->constrained('lookups')->nullOnDelete();
            $table->foreignId('area_id')->nullable()->constrained('lookups')->nullOnDelete();
            $table->string('area_text')->nullable();
            $table->decimal('area_m2', 10, 2)->nullable();
            $table->string('expected_start', 100)->nullable();
            $table->text('description')->nullable();
            $table->foreignId('source_id')->nullable()->constrained('lookups')->nullOnDelete();
            $table->foreignId('status_id')->constrained('lookups');
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('follow_up_at')->nullable()->index();
            $table->foreignId('rejection_reason_id')->nullable()->constrained('lookups')->nullOnDelete();
            $table->text('rejection_note')->nullable();
            $table->string('external_id', 50)->nullable()->unique();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leads');
    }
};
