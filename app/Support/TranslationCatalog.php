<?php

namespace App\Support;

use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Spatie\TranslationLoader\LanguageLine;

/**
 * Every interface string of the application's own language files, with database overrides
 * (spec §5.8). Framework files (validation, pagination, passwords) are not edited here.
 */
class TranslationCatalog
{
    public const GROUPS = ['app', 'auth', 'menu', 'home', 'table', 'users', 'permissions', 'settings', 'activity', 'notifications', 'uploads', 'design', 'clients', 'leads', 'visits', 'price_items', 'quotations', 'pdf', 'projects', 'maintenance', 'finance', 'reports', 'site', 'resources', 'js'];

    public const LOCALES = ['ar', 'en'];

    /** @var array<string, array{ar: array<string, string>, en: array<string, string>}> */
    private array $files = [];

    /**
     * @return Collection<int, array{group: string, key: string, ar: string, en: string, overridden: bool}>
     */
    public function all(): Collection
    {
        $overrides = LanguageLine::query()->whereIn('group', self::GROUPS)->get()
            ->keyBy(fn (LanguageLine $line) => $line->group.'.'.$line->key);

        $rows = collect();

        foreach (self::GROUPS as $group) {
            $files = $this->fileValues($group);

            foreach (array_keys($files['ar'] + $files['en']) as $key) {
                /** @var LanguageLine|null $line */
                $line = $overrides->get($group.'.'.$key);
                $text = $line !== null ? (array) $line->text : [];

                $rows->push([
                    'group' => $group,
                    'key' => (string) $key,
                    'ar' => (string) ($text['ar'] ?? $files['ar'][$key] ?? ''),
                    'en' => (string) ($text['en'] ?? $files['en'][$key] ?? ''),
                    'overridden' => $line !== null,
                ]);
            }
        }

        return $rows;
    }

    public function has(string $group, string $key): bool
    {
        if (! in_array($group, self::GROUPS, true)) {
            return false;
        }

        $files = $this->fileValues($group);

        return isset($files['ar'][$key]) || isset($files['en'][$key]);
    }

    /**
     * "group.key (locale)" for every key present in one locale and missing in the other.
     *
     * @return list<string>
     */
    public function missing(): array
    {
        $missing = [];

        foreach (self::GROUPS as $group) {
            $files = $this->fileValues($group);

            foreach (self::LOCALES as $locale) {
                $other = $locale === 'ar' ? 'en' : 'ar';

                foreach (array_diff_key($files[$other], $files[$locale]) as $key => $value) {
                    $missing[] = "{$group}.{$key} ({$locale})";
                }
            }
        }

        return $missing;
    }

    /**
     * Leaf values of a group as written in the files, flattened to dot keys.
     *
     * @return array{ar: array<string, string>, en: array<string, string>}
     */
    private function fileValues(string $group): array
    {
        if (isset($this->files[$group])) {
            return $this->files[$group];
        }

        $values = [];

        foreach (self::LOCALES as $locale) {
            $path = lang_path("{$locale}/{$group}.php");
            $lines = is_file($path) ? require $path : [];
            $values[$locale] = array_map('strval', Arr::dot(is_array($lines) ? $lines : []));
        }

        return $this->files[$group] = $values;
    }
}
