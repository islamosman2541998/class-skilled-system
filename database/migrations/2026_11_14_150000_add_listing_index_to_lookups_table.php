<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The list screen counts and pages by type among non-deleted rows, ordered by sort_order then
     * newest id (DataTable tie-breaker). Measured with 50,000 rows (docs/plans/PROGRESS.md).
     */
    public function up(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement('CREATE INDEX lookups_listing_index ON lookups (type, deleted_at, sort_order, id DESC)');

            return;
        }

        Schema::table('lookups', function (Blueprint $table) {
            $table->index(['type', 'deleted_at', 'sort_order', 'id'], 'lookups_listing_index');
        });
    }

    public function down(): void
    {
        Schema::table('lookups', function (Blueprint $table) {
            $table->dropIndex('lookups_listing_index');
        });
    }
};
