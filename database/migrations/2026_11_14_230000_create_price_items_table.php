<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('price_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_id')->nullable()->constrained('lookups')->nullOnDelete();
            $table->text('description_ar');
            $table->text('description_en')->nullable();
            $table->foreignId('unit_id')->constrained('lookups');
            $table->decimal('default_price', 14, 2)->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['is_active', 'service_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('price_items');
    }
};
