<?php

namespace App\Services;

use App\Enums\NotificationType;
use App\Models\NotificationDispatch;
use App\Models\User;
use App\Notifications\SystemNotification;
use App\Settings\NotificationSettings;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Notification;

/**
 * Sends in-app notifications following the rules in the notification settings (spec §5.5).
 * A dedupe key makes a send happen once per (type, record, key), whatever calls it again.
 */
class Notifier
{
    public function __construct(private readonly NotificationSettings $settings) {}

    /**
     * Notifies every active user holding one of the roles configured for the type.
     *
     * @param  array<string, scalar|null>  $params
     */
    public function toRoles(NotificationType $type, Model $subject, array $params, string $url, ?string $dedupeKey = null): int
    {
        $rule = $this->settings->rule($type);

        if (! $rule['enabled'] || $rule['roles'] === []) {
            return 0;
        }

        $users = User::query()
            ->where('is_active', true)
            ->whereHas('roles', fn ($query) => $query->whereIn('name', $rule['roles']))
            ->get();

        return $this->send($users, $type, $subject, $params, $url, $dedupeKey);
    }

    /**
     * @param  array<string, scalar|null>  $params
     */
    public function toUser(User $user, NotificationType $type, Model $subject, array $params, string $url, ?string $dedupeKey = null): bool
    {
        if (! $this->settings->rule($type)['enabled'] || ! $user->is_active) {
            return false;
        }

        return $this->send(collect([$user]), $type, $subject, $params, $url, $dedupeKey) === 1;
    }

    /**
     * @param  Collection<int, User>  $users
     * @param  array<string, scalar|null>  $params
     */
    private function send(Collection $users, NotificationType $type, Model $subject, array $params, string $url, ?string $dedupeKey): int
    {
        if ($users->isEmpty() || ($dedupeKey !== null && ! $this->claim($type, $subject, $dedupeKey))) {
            return 0;
        }

        Notification::send($users, new SystemNotification($type, $subject, $params, $url));

        return $users->count();
    }

    /** Records the dispatch; false when it was already recorded. */
    private function claim(NotificationType $type, Model $subject, string $key): bool
    {
        try {
            NotificationDispatch::create([
                'type' => $type->value,
                'subject_type' => $subject->getMorphClass(),
                'subject_id' => $subject->getKey(),
                'dedupe_key' => $key,
            ]);

            return true;
        } catch (UniqueConstraintViolationException) {
            return false;
        }
    }
}
