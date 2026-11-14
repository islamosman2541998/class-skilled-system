<?php

namespace App\Models\Concerns;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * Record visibility by role scope (spec §6): users of an "all records" role see everything, the
 * others only the records assigned to them. DataTable, controllers, media and search all use it.
 */
trait HasRecordScope
{
    /** Column holding the user the record is assigned to. */
    abstract protected static function assigneeColumn(): string;

    /**
     * @param  Builder<static>  $query
     */
    public function scopeVisibleTo(Builder $query, User $user): void
    {
        if (! $user->seesAllRecords()) {
            $query->where($query->qualifyColumn(static::assigneeColumn()), $user->id);
        }
    }

    public function isVisibleTo(User $user): bool
    {
        return $user->seesAllRecords() || (int) $this->getAttribute(static::assigneeColumn()) === $user->id;
    }
}
