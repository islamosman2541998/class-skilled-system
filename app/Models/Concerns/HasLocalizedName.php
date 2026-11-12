<?php

namespace App\Models\Concerns;

/**
 * For models with `name_ar` (required) and `name_en` (optional) columns:
 * `$model->name` is the name in the current language, falling back to Arabic.
 */
trait HasLocalizedName
{
    public function getNameAttribute(): string
    {
        if (app()->getLocale() === 'en' && filled($this->attributes['name_en'] ?? null)) {
            return (string) $this->attributes['name_en'];
        }

        return (string) ($this->attributes['name_ar'] ?? '');
    }
}
