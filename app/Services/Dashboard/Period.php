<?php

namespace App\Services\Dashboard;

use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * The dashboard period (spec §9.1): ?from=&to= as Y-m-d, this month by default. Every figure
 * receives it, and every figure link carries it to its list.
 */
final class Period
{
    public function __construct(public readonly Carbon $from, public readonly Carbon $to) {}

    public static function fromRequest(Request $request): self
    {
        $from = self::parse($request->query('from')) ?? today()->startOfMonth();
        $to = self::parse($request->query('to')) ?? today()->endOfMonth()->startOfDay();

        return $to->lt($from) ? new self($to, $from) : new self($from, $to);
    }

    public static function thisMonth(): self
    {
        return new self(today()->startOfMonth(), today()->endOfMonth()->startOfDay());
    }

    /** The from / to query of the lists. */
    public function query(): array
    {
        return ['from' => $this->from->toDateString(), 'to' => $this->to->toDateString()];
    }

    private static function parse(mixed $value): ?Carbon
    {
        if (! is_string($value) || preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) !== 1) {
            return null;
        }

        try {
            return Carbon::createFromFormat('!Y-m-d', $value) ?: null;
        } catch (\Throwable) {
            return null;
        }
    }
}
