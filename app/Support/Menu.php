<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\Route;

/**
 * Sidebar navigation. Each stage registers its own items; items are filtered by permission per user.
 */
class Menu
{
    /** Group order and their label keys. */
    private const GROUPS = [
        'main' => 'menu.main',
        'sales' => 'menu.sales',
        'projects' => 'menu.projects',
        'finance' => 'menu.finance',
        'resources' => 'menu.resources',
        'admin' => 'menu.admin',
    ];

    /** @var array<string, list<array{label: string, icon: string, route: string, permission: ?string, params?: array<string, mixed>, active_routes?: string|list<string>}>> */
    private static array $items = [
        'main' => [
            ['label' => 'app.home', 'icon' => 'home', 'route' => 'home', 'permission' => null],
        ],
    ];

    /**
     * @param  array{label: string, icon: string, route: string, permission?: ?string, params?: array<string, mixed>, active_routes?: string|list<string>}  $item
     */
    public static function register(string $group, array $item): void
    {
        $item['permission'] ??= null;

        foreach (self::$items[$group] ?? [] as $existing) {
            if ($existing['route'] === $item['route'] && ($existing['params'] ?? []) === ($item['params'] ?? [])
                && $existing['permission'] === $item['permission']) {
                return;
            }
        }

        self::$items[$group][] = $item;
    }

    /**
     * @return list<array{key: string, label: string, items: list<array<string, mixed>>}>
     */
    public static function items(User $user): array
    {
        $groups = [];

        foreach (self::GROUPS as $key => $label) {
            $items = [];

            foreach (self::$items[$key] ?? [] as $item) {
                if (! Route::has($item['route'])) {
                    continue;
                }

                if ($item['permission'] !== null && ! $user->can($item['permission'])) {
                    continue;
                }

                $item['url'] = route($item['route'], $item['params'] ?? []);
                $item['active'] = self::isActive($item);
                $items[] = $item;
            }

            if ($items !== []) {
                $groups[] = ['key' => $key, 'label' => $label, 'items' => $items];
            }
        }

        return $groups;
    }

    /**
     * @param  array<string, mixed>  $item
     */
    private static function isActive(array $item): bool
    {
        $request = request();

        if ($item['route'] === 'home') {
            return $request->routeIs('home');
        }

        if (isset($item['active_routes'])) {
            return $request->routeIs(...(array) $item['active_routes']);
        }

        // A section stays highlighted on all of its pages (index, create, edit, other lists).
        return $request->routeIs(explode('.', $item['route'])[0].'.*');
    }
}
