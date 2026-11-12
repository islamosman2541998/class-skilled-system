<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('table_preferences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('table_key', 80);
            $table->json('hidden_columns')->nullable();
            $table->unsignedSmallInteger('per_page')->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'table_key']);
        });

        Schema::create('saved_filters', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('table_key', 80);
            $table->string('name', 80);
            $table->json('state');
            $table->timestamps();
            $table->index(['user_id', 'table_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('saved_filters');
        Schema::dropIfExists('table_preferences');
    }
};
