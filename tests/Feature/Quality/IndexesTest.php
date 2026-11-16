<?php

use Illuminate\Support\Facades\Schema;

/**
 * Spec §11: indexes on foreign keys, status, date, number and search columns of the big lists.
 * A foreign key column counts as covered by its constraint (MySQL indexes those on its own).
 */
const BIG_LISTS = [
    'clients', 'leads', 'site_visits', 'quotations', 'projects', 'project_stages', 'project_installments', 'variations',
    'maintenance_requests', 'invoices', 'receipts', 'expenses', 'daily_reports', 'claims', 'suppliers', 'purchases',
    'subcontracts', 'payment_vouchers', 'attendances', 'equipment_assignments',
];

it('indexes foreign keys, status, number, date and deleted_at columns of the big lists', function () {
    $missing = [];

    foreach (BIG_LISTS as $table) {
        $leading = collect(Schema::getIndexes($table))->map(fn (array $index) => $index['columns'][0])->unique()->all();
        $constrained = collect(Schema::getForeignKeys($table))->map(fn (array $key) => $key['columns'][0])->all();

        foreach (Schema::getColumnListing($table) as $column) {
            $needs = $column === 'status' || $column === 'number' || $column === 'deleted_at'
                || str_ends_with($column, '_date') || str_ends_with($column, '_id');

            if (! $needs || in_array($column, $leading, true)) {
                continue;
            }

            if (str_ends_with($column, '_id') && in_array($column, $constrained, true)) {
                continue;
            }

            $missing[] = "{$table}.{$column}";
        }
    }

    expect($missing)->toBe([]);
});
