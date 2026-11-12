<?php

namespace App\Models;

use App\Contracts\GuardsDeletion;
use App\Models\Concerns\LogsModelActivity;
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
class Role extends SpatieRole implements GuardsDeletion
{
    use LogsModelActivity;

    public const SCOPE_ALL = 'all';

    public const SCOPE_ASSIGNED = 'assigned';

    /** @var list<string> */
    protected array $activityAttributes = ['name', 'label_ar', 'label_en', 'scope'];

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

    public function deletionBlockedReason(User $by): ?string
    {
        if ($this->is_system) {
            return __('users.role_is_system');
        }

        return $this->users()->exists() ? __('users.role_in_use') : null;
    }
}
