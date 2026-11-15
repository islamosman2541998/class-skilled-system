<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Claim setup of a project billed by claims (spec §5.9) and soft-deleted contract items. */
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->decimal('retention_percent', 5, 2)->default(0)->after('billing_method');
            $table->decimal('contract_discount_percent', 7, 4)->default(0)->after('retention_percent');
            $table->decimal('advance_amount', 14, 2)->default(0)->after('contract_discount_percent');
        });

        Schema::table('project_items', function (Blueprint $table) {
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::table('project_items', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });

        Schema::table('projects', function (Blueprint $table) {
            $table->dropColumn(['retention_percent', 'contract_discount_percent', 'advance_amount']);
        });
    }
};
