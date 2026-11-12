<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lookups', function (Blueprint $table) {
            $table->id();
            $table->string('type', 40);
            $table->foreignId('parent_id')->nullable()->constrained('lookups')->restrictOnDelete();
            $table->string('name_ar', 120);
            $table->string('name_en', 120)->nullable();
            $table->string('code', 40)->nullable();
            $table->string('color', 20)->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('is_system')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['type', 'is_active', 'sort_order']);
            $table->index(['type', 'name_ar']);
            $table->index(['type', 'code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lookups');
    }
};
