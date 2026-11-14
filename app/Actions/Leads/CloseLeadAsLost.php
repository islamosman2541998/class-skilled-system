<?php

namespace App\Actions\Leads;

use App\Models\Lead;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class CloseLeadAsLost
{
    public function handle(Lead $lead, int $reasonId, ?string $note, User $by): void
    {
        DB::transaction(function () use ($lead, $reasonId, $note, $by) {
            $lead->rejection_reason_id = $reasonId;
            $lead->rejection_note = $note;
            $lead->follow_up_at = null;
            $lead->save();

            $lead->setStatusCode('lost', $by);
        });
    }
}
