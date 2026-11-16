<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Indexes the performance review found missing (spec §11): soft-delete and date columns that
     * the lists filter on. Every list query reads `deleted_at IS NULL`.
     *
     * @var array<string, list<string>>
     */
    private const INDEXES = [
        'clients' => ['deleted_at'],
        'leads' => ['deleted_at'],
        'site_visits' => ['deleted_at'],
        'quotations' => ['deleted_at'],
        'projects' => ['planned_end_date', 'actual_end_date', 'deleted_at'],
        'project_stages' => ['deleted_at'],
        'project_installments' => ['due_date', 'deleted_at'],
        'variations' => ['deleted_at'],
        'maintenance_requests' => ['deleted_at'],
        'invoices' => ['deleted_at'],
        'receipts' => ['cheque_date'],
        'expenses' => ['deleted_at'],
        'daily_reports' => ['deleted_at'],
        'claims' => ['deleted_at'],
        'suppliers' => ['deleted_at'],
        'purchases' => ['deleted_at'],
        'subcontracts' => ['end_date', 'deleted_at'],
        'attendances' => ['work_date', 'status'],
        'equipment_assignments' => ['deleted_at'],
    ];

    public function up(): void
    {
        foreach (self::INDEXES as $table => $columns) {
            Schema::table($table, function (Blueprint $blueprint) use ($columns) {
                foreach ($columns as $column) {
                    $blueprint->index($column);
                }
            });
        }
    }

    public function down(): void
    {
        foreach (self::INDEXES as $table => $columns) {
            Schema::table($table, function (Blueprint $blueprint) use ($table, $columns) {
                foreach ($columns as $column) {
                    $blueprint->dropIndex("{$table}_{$column}_index");
                }
            });
        }
    }
};
