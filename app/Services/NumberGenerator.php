<?php

namespace App\Services;

use App\Enums\DocumentType;
use App\Models\DocumentSequence;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

/**
 * Issues document numbers (spec §5.9).
 *
 * Call next() inside the same database transaction that saves the document: the row lock
 * prevents two users from getting the same number, and a rollback gives the number back.
 */
class NumberGenerator
{
    public function next(DocumentType $type, ?CarbonInterface $date = null): string
    {
        $year = (int) ($date ?? now())->format('Y');

        return DB::transaction(function () use ($type, $year) {
            $sequence = DocumentSequence::query()->where('type', $type->value)->lockForUpdate()->first()
                ?? DocumentSequence::create(DocumentSequence::defaults($type));

            $number = $sequence->upcoming($year);

            $sequence->forceFill(['next_number' => $number + 1, 'year' => $year])->save();

            return $sequence->format($number, $year);
        });
    }

    /** The next number without issuing it. */
    public function preview(DocumentType $type, ?CarbonInterface $date = null): string
    {
        $year = (int) ($date ?? now())->format('Y');

        $sequence = DocumentSequence::query()->where('type', $type->value)->first()
            ?? new DocumentSequence(DocumentSequence::defaults($type));

        return $sequence->format($sequence->upcoming($year), $year);
    }
}
