<?php

namespace App\Actions\Site;

use App\Enums\ClaimStatus;
use App\Models\Claim;
use App\Models\User;
use App\Services\ActivityLogger;
use DomainException;
use Illuminate\Support\Facades\DB;

/**
 * Returns a submitted claim to draft with a note for the engineer (spec §8.14). The number stays.
 */
class ReturnClaim
{
    /**
     * @throws DomainException when the claim is not submitted
     */
    public function handle(Claim $claim, string $note, User $by): Claim
    {
        return DB::transaction(function () use ($claim, $note) {
            $locked = Claim::query()->lockForUpdate()->findOrFail($claim->id);

            if ($locked->status !== ClaimStatus::Submitted) {
                throw new DomainException(__('site.claims.not_submitted'));
            }

            $locked->status = ClaimStatus::Draft;
            $locked->return_note = $note;
            $locked->save();

            ActivityLogger::record('status_changed', $locked, ['old' => ['status' => ClaimStatus::Submitted->value], 'attributes' => ['status' => ClaimStatus::Draft->value, 'return_note' => $note]], 'claims');

            return $locked;
        });
    }
}
