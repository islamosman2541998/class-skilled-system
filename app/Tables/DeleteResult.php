<?php

namespace App\Tables;

final class DeleteResult
{
    public function __construct(
        public int $deleted = 0,
        public int $skipped = 0,
        public ?string $firstReason = null,
    ) {}
}
