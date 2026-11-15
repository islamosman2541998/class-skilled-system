<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A read-only row of a report: report tables query ReportRow::query()->fromSub($aggregate, 'r'),
 * so search, filters, sorting, totals and export of the standard table work on aggregates (spec §9.2).
 * Every aggregate selects a unique "id" column for stable paging.
 */
class ReportRow extends Model
{
    protected $table = 'r';

    public $timestamps = false;

    protected $guarded = [];
}
