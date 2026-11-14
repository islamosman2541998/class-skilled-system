<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clients', function (Blueprint $table) {
            $table->id();
            $table->string('type', 20);
            $table->string('name_ar');
            $table->string('name_en')->nullable();
            $table->string('phone', 40);
            $table->string('phone_normalized', 20)->nullable()->index();
            $table->string('phone2', 40)->nullable();
            $table->string('whatsapp', 40)->nullable();
            $table->string('email')->nullable()->index();
            $table->string('trn', 15)->nullable()->index();
            $table->foreignId('emirate_id')->nullable()->constrained('lookups')->nullOnDelete();
            $table->foreignId('area_id')->nullable()->constrained('lookups')->nullOnDelete();
            $table->text('address')->nullable();
            $table->foreignId('source_id')->nullable()->constrained('lookups')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index('name_ar');
            $table->index('created_at');
            $table->index('type');
        });

        Schema::create('client_contacts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('position')->nullable();
            $table->string('phone', 40)->nullable();
            $table->string('email')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('client_contacts');
        Schema::dropIfExists('clients');
    }
};
