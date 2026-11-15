<?php

namespace App\Actions\Site;

use App\Enums\ClaimStatus;
use App\Enums\DocumentType;
use App\Models\Claim;
use App\Models\User;
use App\Services\ActivityLogger;
use App\Services\NumberGenerator;
use Illuminate\Support\Facades\DB;

/**
 * Submits a draft claim (spec §5.9, §8.14): reserves its number once and freezes the lines.
 * A claim returned to draft keeps its number; a second call changes nothing.
 */
class SubmitClaim
{
    public function __construct(private readonly NumberGenerator $numbers) {}

    public function handle(Claim $claim, User $by): Claim
    {
        return DB::transaction(function () use ($claim, $by) {
            $locked = Claim::query()->lockForUpdate()->findOrFail($claim->id);

            if ($locked->status !== ClaimStatus::Draft) {
                return $locked;
            }

            $locked->number ??= $this->numbers->next(DocumentType::Claim, $locked->period_to);
            $locked->status = ClaimStatus::Submitted;
            $locked->submitted_at = now();
            $locked->submitted_by = $by->id;
            $locked->save();

            ActivityLogger::record('status_changed', $locked, ['old' => ['status' => ClaimStatus::Draft->value], 'attributes' => ['status' => ClaimStatus::Submitted->value, 'number' => $locked->number]], 'claims');

            return $locked;
        });
    }
}
