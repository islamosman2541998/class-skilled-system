<?php

namespace App\Settings;

use App\Enums\NotificationType;
use Spatie\LaravelSettings\Settings;

class NotificationSettings extends Settings
{
    // Overrides keyed by NotificationType value, each with enabled, days and roles. No @var on purpose:
    // spatie/laravel-settings resolves docblock types and cannot read nested array shapes.
    public array $rules;

    public static function group(): string
    {
        return 'notifications';
    }

    /**
     * Stored values over the type's defaults.
     *
     * @return array{enabled: bool, days: ?int, roles: list<string>}
     */
    public function rule(NotificationType $type): array
    {
        $stored = $this->rules[$type->value] ?? [];

        return [
            'enabled' => (bool) ($stored['enabled'] ?? true),
            'days' => $type->usesDays() ? (int) ($stored['days'] ?? $type->defaultDays()) : null,
            'roles' => array_values($stored['roles'] ?? $type->defaultRoles()),
        ];
    }
}
