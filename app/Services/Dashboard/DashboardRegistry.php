<?php

namespace App\Services\Dashboard;

use App\Models\User;
use Closure;

/**
 * Dashboard figures (spec §9.1), filled by the module providers. A figure shows when the user holds
 * its permissions, so the dashboard follows the role without role names in code. Each figure
 * links to the list that produced it, filtered the same way.
 */
class DashboardRegistry
{
    public const FORMATS = ['count', 'money', 'percent'];

    /**
     * @var array<string, array{group: string, permissions: list<string>, figure: Closure, url: Closure|null, format: string}>
     */
    private static array $figures = [];

    /**
     * @param  string|list<string>  $permission
     * @param  Closure(User, Period): (string|int|float|null)  $figure
     * @param  (Closure(User, Period): ?string)|null  $url
     */
    public static function register(string $key, string|array $permission, Closure $figure, ?Closure $url = null, string $group = 'general', string $format = 'count'): void
    {
        self::$figures[$key] = [
            'group' => $group,
            'permissions' => (array) $permission,
            'figure' => $figure,
            'url' => $url,
            'format' => in_array($format, self::FORMATS, true) ? $format : 'count',
        ];
    }

    public static function allows(User $user, string $key): bool
    {
        $figure = self::$figures[$key] ?? null;

        return $figure !== null && collect($figure['permissions'])->every(fn (string $permission) => $user->can($permission));
    }

    /**
     * The user's figures for the period, grouped in registration order.
     *
     * @return array<string, list<array{key: string, label: string, raw: string|int|float|null, value: string, url: ?string}>>
     */
    public static function for(User $user, Period $period): array
    {
        $groups = [];

        foreach (self::$figures as $key => $figure) {
            if (! self::allows($user, $key)) {
                continue;
            }

            $raw = ($figure['figure'])($user, $period);
            $groups[$figure['group']][] = [
                'key' => $key,
                'label' => __('home.figures.'.$key),
                'raw' => $raw,
                'value' => self::format($raw, $figure['format']),
                'url' => $figure['url'] ? ($figure['url'])($user, $period) : null,
            ];
        }

        return $groups;
    }

    private static function format(string|int|float|null $raw, string $format): string
    {
        return match ($format) {
            'money' => money((string) ($raw ?? 0)),
            'percent' => $raw === null ? __('home.no_value') : number_format((float) $raw, 2).'%',
            default => (string) (int) $raw,
        };
    }
}
