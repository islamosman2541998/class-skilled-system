<?php

namespace App\Services;

use Illuminate\Database\Eloquent\Model;

/**
 * Records events that are not plain model changes: sign-in, export, print, status change,
 * settings update, permission change (spec §5.7).
 */
class ActivityLogger
{
    /**
     * @param  array<string, mixed>  $properties
     */
    public static function record(string $event, ?Model $subject = null, array $properties = [], ?string $module = null): void
    {
        $logger = activity($module ?? ($subject?->getTable() ?? 'system'))->event($event)->withProperties($properties);

        if ($subject !== null) {
            $logger->performedOn($subject);
        }

        if (auth()->check()) {
            $logger->causedBy(auth()->user());
        }

        $logger->log($event);
    }

    /**
     * Records a change of a plain set of values (settings, permission lists) keeping only what differs.
     *
     * @param  array<string, mixed>  $old
     * @param  array<string, mixed>  $new
     * @param  array<string, mixed>  $extra
     */
    public static function recordChange(string $event, array $old, array $new, ?Model $subject = null, array $extra = [], ?string $module = null): void
    {
        $changedOld = [];
        $changedNew = [];

        foreach ($new as $key => $value) {
            if (($old[$key] ?? null) !== $value) {
                $changedOld[$key] = $old[$key] ?? null;
                $changedNew[$key] = $value;
            }
        }

        if ($changedNew === []) {
            return;
        }

        self::record($event, $subject, [...$extra, 'old' => $changedOld, 'attributes' => $changedNew], $module);
    }
}
