<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Marks the retention release invoice of a project billed by claims (spec §5.9). */
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->boolean('retention_release')->default(false)->after('claim_id');
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn('retention_release');
        });
    }
};
