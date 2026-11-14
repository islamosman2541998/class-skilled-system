<?php

namespace App\Actions\Leads;

use App\Enums\NotificationType;
use App\Models\Lead;
use App\Models\User;
use App\Services\Notifier;

/**
 * Sets the responsible user of a lead and tells them (spec §5.5), unless they assigned themselves.
 */
class AssignLead
{
    public function __construct(private readonly Notifier $notifier) {}

    public function handle(Lead $lead, ?User $to, User $by): void
    {
        $changed = $lead->assigned_to !== $to?->id;

        if ($changed) {
            $lead->assigned_to = $to?->id;
            $lead->save();
        }

        $this->notifyIfNeeded($lead, $to, $by, $changed);
    }

    /** For a lead saved elsewhere: notifies when the assignee is new and is not the actor. */
    public function notifyIfNeeded(Lead $lead, ?User $to, User $by, bool $changed = true): void
    {
        if ($to === null || ! $changed || $to->is($by)) {
            return;
        }

        $this->notifier->toUser($to, NotificationType::Assigned, $lead, ['number' => $lead->number], route('leads.show', $lead));
    }
}
