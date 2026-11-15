<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Progress claims (spec §5.9, §8.14) and the claim link on invoices. */
    public function up(): void
    {
        Schema::create('claims', function (Blueprint $table) {
            $table->id();
            $table->string('number', 40)->nullable()->unique();
            $table->foreignId('project_id')->constrained()->restrictOnDelete();
            $table->foreignId('client_id')->constrained()->restrictOnDelete();
            $table->date('period_from');
            $table->date('period_to');
            $table->string('status', 20)->index();
            $table->decimal('vat_rate', 5, 2);
            $table->decimal('retention_percent', 5, 2)->default(0);
            foreach (['work_previous', 'work_current', 'work_cumulative', 'discount_amount', 'gross_amount', 'retention_amount', 'advance_recovery', 'net_amount', 'vat_amount', 'total'] as $column) {
                $table->decimal($column, 14, 2)->default(0);
            }
            $table->text('notes')->nullable();
            $table->text('return_note')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['project_id', 'status']);
            $table->index('period_to');
        });

        Schema::create('claim_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('claim_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_item_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('variation_item_id')->nullable()->constrained()->nullOnDelete();
            $table->text('description_ar');
            $table->text('description_en')->nullable();
            $table->foreignId('unit_id')->nullable()->constrained('lookups')->nullOnDelete();
            $table->decimal('contract_quantity', 12, 3);
            $table->decimal('unit_price', 14, 2);
            $table->decimal('previous_quantity', 12, 3)->default(0);
            $table->decimal('current_quantity', 12, 3)->default(0);
            $table->decimal('cumulative_quantity', 12, 3)->default(0);
            $table->decimal('current_amount', 14, 2)->default(0);
            $table->decimal('cumulative_amount', 14, 2)->default(0);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->foreignId('claim_id')->nullable()->after('installment_id')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropConstrainedForeignId('claim_id');
        });

        Schema::dropIfExists('claim_lines');
        Schema::dropIfExists('claims');
    }
};
