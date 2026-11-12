<?php

namespace App\Models\Concerns;

use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * Records create / update / delete / restore of a model in the activity log (spec §5.7):
 * only the attributes that changed, with their old and new values.
 *
 * Optional on the model:
 *   protected array $activityAttributes = [...];  log exactly these instead of the fillable ones
 *   protected array $activityHidden = [...];      never log these
 *   public function activityModule(): string      log name (defaults to the table name)
 */
trait LogsModelActivity
{
    use LogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        $options = LogOptions::defaults()
            ->useLogName($this->activityModule())
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();

        $options = property_exists($this, 'activityAttributes')
            ? $options->logOnly($this->activityAttributes)
            : $options->logFillable();

        return $options->logExcept(property_exists($this, 'activityHidden') ? $this->activityHidden : []);
    }

    public function activityModule(): string
    {
        return $this->getTable();
    }
}
