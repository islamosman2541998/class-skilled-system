<?php

namespace App\Models;

use Spatie\Permission\Models\Role as SpatieRole;

/**
 * @property int $id
 * @property string $name
 * @property string $label_ar
 * @property string|null $label_en
 * @property string $scope all|assigned: whether members see every record or only those assigned to them
 * @property bool $is_system
 * @property-read string $label
 */
class Role extends SpatieRole
{
    public const SCOPE_ALL = 'all';

    public const SCOPE_ASSIGNED = 'assigned';

    protected function casts(): array
    {
        return [
            'is_system' => 'boolean',
        ];
    }

    public function getLabelAttribute(): string
    {
        if (app()->getLocale() === 'en' && $this->label_en) {
            return $this->label_en;
        }

        return $this->label_ar ?: $this->name;
    }
}
