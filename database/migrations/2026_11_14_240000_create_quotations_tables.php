<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quotations', function (Blueprint $table) {
            $table->id();
            $table->string('number', 40)->unique();
            $table->unsignedSmallInteger('revision')->default(0);
            $table->foreignId('parent_id')->nullable()->constrained('quotations')->nullOnDelete();
            $table->dateTime('superseded_at')->nullable();
            $table->foreignId('client_id')->constrained();
            $table->foreignId('lead_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('service_id')->nullable()->constrained('lookups')->nullOnDelete();
            $table->string('title_ar');
            $table->string('title_en')->nullable();
            $table->string('location')->nullable();
            $table->foreignId('emirate_id')->nullable()->constrained('lookups')->nullOnDelete();
            $table->date('issue_date')->index();
            $table->date('valid_until');
            $table->foreignId('assigned_to')->constrained('users');
            $table->string('status', 20);
            $table->string('discount_type', 10)->nullable();
            $table->decimal('discount_value', 14, 2)->default(0);
            $table->decimal('vat_rate', 5, 2);
            $table->decimal('subtotal', 14, 2)->default(0);
            $table->decimal('discount_amount', 14, 2)->default(0);
            $table->decimal('taxable_amount', 14, 2)->default(0);
            $table->decimal('vat_amount', 14, 2)->default(0);
            $table->decimal('total', 14, 2)->default(0);
            $table->text('terms_ar')->nullable();
            $table->text('terms_en')->nullable();
            $table->text('notes')->nullable();
            $table->dateTime('sent_at')->nullable();
            $table->dateTime('accepted_at')->nullable();
            $table->dateTime('rejected_at')->nullable();
            $table->foreignId('rejection_reason_id')->nullable()->constrained('lookups')->nullOnDelete();
            $table->text('rejection_note')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'valid_until']);
            $table->index('assigned_to');
            $table->index('total');
        });

        Schema::create('quotation_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quotation_id')->constrained()->cascadeOnDelete();
            $table->string('section_ar')->nullable();
            $table->string('section_en')->nullable();
            $table->foreignId('price_item_id')->nullable()->constrained()->nullOnDelete();
            $table->text('description_ar');
            $table->text('description_en')->nullable();
            $table->foreignId('unit_id')->nullable()->constrained('lookups')->nullOnDelete();
            $table->decimal('quantity', 12, 3);
            $table->decimal('unit_price', 14, 2);
            $table->decimal('line_total', 14, 2);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('quotation_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quotation_id')->constrained()->cascadeOnDelete();
            $table->string('label_ar');
            $table->string('label_en')->nullable();
            $table->decimal('percent', 5, 2);
            $table->decimal('amount', 14, 2);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quotation_payments');
        Schema::dropIfExists('quotation_items');
        Schema::dropIfExists('quotations');
    }
};
