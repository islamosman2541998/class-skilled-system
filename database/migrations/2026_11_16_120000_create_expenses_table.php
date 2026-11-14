<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('expenses', function (Blueprint $table) {
            $table->id();
            $table->string('number', 30)->unique();
            // Empty for a general expense of the company (spec §8.12).
            $table->foreignId('project_id')->nullable()->constrained();
            $table->foreignId('category_id')->constrained('lookups');
            // Free text until Stage 5 adds suppliers.
            $table->string('supplier_name')->nullable();
            $table->date('expense_date')->index();
            $table->decimal('amount', 14, 2);
            $table->decimal('vat_rate', 5, 2)->default(0);
            $table->decimal('vat_amount', 14, 2)->default(0);
            $table->decimal('total', 14, 2);
            $table->foreignId('payment_method_id')->nullable()->constrained('lookups')->nullOnDelete();
            $table->foreignId('paid_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('supplier_invoice_no', 100)->nullable();
            $table->text('description')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['project_id', 'expense_date']);
            $table->index('category_id');
            $table->index('amount');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('expenses');
    }
};
