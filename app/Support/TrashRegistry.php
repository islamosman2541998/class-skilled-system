<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Model;

/**
 * Soft-deletable models whose trash is purged after the retention period (spec §5.2).
 */
class TrashRegistry
{
    /** @var list<class-string<Model>> */
    private static array $models = [];

    /**
     * @param  class-string<Model>  $model
     */
    public static function register(string $model): void
    {
        if (! in_array($model, self::$models, true)) {
            self::$models[] = $model;
        }
    }

    /**
     * @return list<class-string<Model>>
     */
    public static function models(): array
    {
        return self::$models;
    }
}
