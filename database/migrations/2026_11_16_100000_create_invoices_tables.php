<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            // Reserved when the invoice is issued, so drafts have none (spec §5.9).
            $table->string('number', 30)->nullable()->unique();
            $table->foreignId('project_id')->constrained();
            $table->foreignId('client_id')->constrained();
            $table->foreignId('installment_id')->nullable()->constrained('project_installments')->nullOnDelete();
            $table->foreignId('replaces_id')->nullable()->constrained('invoices')->nullOnDelete();
            $table->date('issue_date')->index();
            $table->date('due_date')->index();
            $table->string('status', 20)->index();
            $table->decimal('vat_rate', 5, 2);
            $table->decimal('subtotal', 14, 2)->default(0);
            $table->decimal('vat_amount', 14, 2)->default(0);
            $table->decimal('total', 14, 2)->default(0);
            $table->decimal('paid_amount', 14, 2)->default(0);
            $table->text('notes')->nullable();
            $table->text('terms_ar')->nullable();
            $table->text('terms_en')->nullable();
            $table->dateTime('issued_at')->nullable();
            $table->foreignId('issued_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('cancel_reason')->nullable();
            $table->dateTime('cancelled_at')->nullable();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['project_id', 'status']);
            $table->index(['client_id', 'status']);
            $table->index('total');
        });

        Schema::create('invoice_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();
            $table->text('description_ar');
            $table->text('description_en')->nullable();
            $table->foreignId('unit_id')->nullable()->constrained('lookups')->nullOnDelete();
            $table->decimal('quantity', 12, 3);
            $table->decimal('unit_price', 14, 2);
            $table->decimal('line_total', 14, 2);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::table('projects', function (Blueprint $table) {
            $table->decimal('invoiced_net', 14, 2)->default(0)->after('invoiced_total');
        });
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropColumn('invoiced_net');
        });
        Schema::dropIfExists('invoice_items');
        Schema::dropIfExists('invoices');
    }
};
