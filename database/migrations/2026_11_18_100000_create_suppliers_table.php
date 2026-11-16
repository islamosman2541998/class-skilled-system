<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Suppliers and subcontractors (spec §8.15). */
    public function up(): void
    {
        Schema::create('suppliers', function (Blueprint $table) {
            $table->id();
            $table->string('type', 20)->index();
            $table->string('name_ar', 190);
            $table->string('name_en', 190)->nullable();
            $table->foreignId('specialty_id')->nullable()->constrained('lookups')->nullOnDelete();
            $table->string('phone', 30)->nullable();
            $table->string('email', 190)->nullable();
            $table->string('trn', 15)->nullable();
            $table->string('address', 255)->nullable();
            $table->string('bank_name', 190)->nullable();
            $table->string('bank_account_name', 190)->nullable();
            $table->string('bank_iban', 34)->nullable();
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->index('name_ar');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('suppliers');
    }
};
