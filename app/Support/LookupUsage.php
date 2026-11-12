<?php

namespace App\Support;

use App\Enums\LookupType;
use App\Models\Lookup;
use Closure;

/**
 * Lets each module say when a list row is in use, so it is deactivated instead of deleted (spec §7).
 */
class LookupUsage
{
    /** @var array<string, list<Closure(Lookup): bool>> */
    private static array $checks = [];

    /**
     * @param  Closure(Lookup): bool  $inUse
     */
    public static function register(LookupType $type, Closure $inUse): void
    {
        self::$checks[$type->value][] = $inUse;
    }

    public static function inUse(Lookup $lookup): bool
    {
        foreach (self::$checks[$lookup->type->value] ?? [] as $check) {
            if ($check($lookup)) {
                return true;
            }
        }

        return false;
    }
}
