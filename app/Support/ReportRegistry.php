<?php

namespace App\Support;

use App\Models\User;

/**
 * The reports page lists every registered report the user may open (spec §9.2). Each report is a
 * report table component plus the permissions it needs besides reports.view.
 */
class ReportRegistry
{
    /** @var array<string, array{component: string, label: string, hint: string, group: string, permissions: list<string>}> */
    private static array $reports = [];

    /**
     * @param  list<string>  $permissions
     */
    public static function register(string $key, string $component, string $group, array $permissions = []): void
    {
        self::$reports[$key] = [
            'component' => $component,
            'label' => 'reports.titles.'.$key,
            'hint' => 'reports.hints.'.$key,
            'group' => $group,
            'permissions' => $permissions,
        ];
    }

    /**
     * @return array{component: string, label: string, hint: string, group: string, permissions: list<string>}|null
     */
    public static function find(string $key): ?array
    {
        return self::$reports[$key] ?? null;
    }

    public static function allows(User $user, string $key): bool
    {
        $report = self::find($key);

        return $report !== null && $user->can('reports.view')
            && collect($report['permissions'])->every(fn (string $permission) => $user->can($permission));
    }

    /**
     * Reports the user may open, grouped.
     *
     * @return array<string, array<string, array{component: string, label: string, hint: string, group: string, permissions: list<string>}>>
     */
    public static function forUser(User $user): array
    {
        $groups = [];

        foreach (self::$reports as $key => $report) {
            if (self::allows($user, $key)) {
                $groups[$report['group']][$key] = $report;
            }
        }

        return $groups;
    }
}
