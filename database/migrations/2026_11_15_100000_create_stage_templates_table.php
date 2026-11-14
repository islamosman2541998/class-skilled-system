<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stage_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_id')->constrained('lookups');
            $table->string('name_ar');
            $table->string('name_en')->nullable();
            $table->decimal('weight', 5, 2);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['service_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stage_templates');
    }
};
