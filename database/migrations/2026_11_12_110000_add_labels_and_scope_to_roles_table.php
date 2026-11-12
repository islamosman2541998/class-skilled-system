<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('roles', function (Blueprint $table) {
            $table->string('label_ar')->default('');
            $table->string('label_en')->nullable();
            $table->string('scope', 10)->default('all');
            $table->boolean('is_system')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('roles', function (Blueprint $table) {
            $table->dropColumn(['label_ar', 'label_en', 'scope', 'is_system']);
        });
    }
};
