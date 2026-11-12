<?php

namespace App\Contracts;

use App\Models\User;

/**
 * A model that can refuse deletion, for example because other records depend on it (spec §5.2).
 */
interface GuardsDeletion
{
    /**
     * A translated sentence explaining why this record cannot be deleted by the given user,
     * or null when it can.
     */
    public function deletionBlockedReason(User $by): ?string;
}
