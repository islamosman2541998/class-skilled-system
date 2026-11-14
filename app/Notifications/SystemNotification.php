<?php

namespace App\Notifications;

use App\Enums\NotificationType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Notification;

/**
 * Every in-app notification (spec §5.5). The title is translated when shown, so a user who switches
 * language sees old notifications in the new language too.
 */
class SystemNotification extends Notification
{
    /**
     * @param  array<string, scalar|null>  $params
     */
    public function __construct(
        public readonly NotificationType $type,
        public readonly Model $subject,
        public readonly array $params,
        public readonly string $url,
    ) {}

    /**
     * The sentence shown for stored notification data, in the current language.
     *
     * @param  array<string, mixed>  $data
     */
    public static function title(array $data): string
    {
        $key = (string) ($data['title_key'] ?? '');
        $params = array_map(fn ($value) => (string) $value, (array) ($data['params'] ?? []));

        return $key !== '' && __($key) !== $key ? __($key, $params) : (string) ($data['type'] ?? '');
    }

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function databaseType(object $notifiable): string
    {
        return $this->type->value;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => $this->type->value,
            'title_key' => 'notifications.types.'.$this->type->value,
            'params' => $this->params,
            'url' => $this->url,
            'subject_type' => $this->subject->getMorphClass(),
            'subject_id' => $this->subject->getKey(),
        ];
    }
}
