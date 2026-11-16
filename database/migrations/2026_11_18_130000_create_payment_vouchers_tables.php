<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Payment vouchers to suppliers and their allocations over purchase invoices (spec §8.16). */
    public function up(): void
    {
        Schema::create('payment_vouchers', function (Blueprint $table) {
            $table->id();
            $table->string('number', 40)->unique();
            $table->foreignId('supplier_id')->constrained()->restrictOnDelete();
            $table->date('voucher_date')->index();
            $table->decimal('amount', 14, 2);
            $table->foreignId('payment_method_id')->constrained('lookups')->restrictOnDelete();
            $table->string('reference', 100)->nullable();
            $table->text('notes')->nullable();
            $table->string('status', 20)->index();
            $table->text('cancel_reason')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('payment_allocations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payment_voucher_id')->constrained()->cascadeOnDelete();
            $table->foreignId('purchase_id')->constrained()->restrictOnDelete();
            $table->decimal('amount', 14, 2);
            $table->timestamps();
            $table->unique(['payment_voucher_id', 'purchase_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_allocations');
        Schema::dropIfExists('payment_vouchers');
    }
};
