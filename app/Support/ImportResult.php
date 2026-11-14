<?php

namespace App\Support;

/** Outcome of a spreadsheet import: counts and translated per-row errors. */
final readonly class ImportResult
{
    /**
     * @param  list<string>  $errors
     */
    public function __construct(
        public int $created,
        public int $updated,
        public array $errors,
    ) {}
}
