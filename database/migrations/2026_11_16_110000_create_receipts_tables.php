<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A receipt is cancelled with a reason, never deleted (spec §8.11), so it has no deleted_at.
        Schema::create('receipts', function (Blueprint $table) {
            $table->id();
            $table->string('number', 30)->unique();
            $table->foreignId('client_id')->constrained();
            $table->foreignId('project_id')->nullable()->constrained();
            $table->date('receipt_date')->index();
            $table->decimal('amount', 14, 2);
            $table->foreignId('payment_method_id')->constrained('lookups');
            $table->string('reference', 100)->nullable();
            $table->date('cheque_date')->nullable();
            $table->foreignId('received_by')->constrained('users');
            $table->text('notes')->nullable();
            $table->string('status', 20)->index();
            $table->text('cancel_reason')->nullable();
            $table->dateTime('cancelled_at')->nullable();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['client_id', 'status']);
            $table->index(['project_id', 'status']);
            $table->index('amount');
        });

        Schema::create('receipt_allocations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('receipt_id')->constrained()->cascadeOnDelete();
            $table->foreignId('invoice_id')->constrained();
            $table->decimal('amount', 14, 2);
            $table->timestamps();

            $table->unique(['receipt_id', 'invoice_id']);
            $table->index('invoice_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('receipt_allocations');
        Schema::dropIfExists('receipts');
    }
};
