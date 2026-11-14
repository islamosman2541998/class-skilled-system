<?php

namespace App\Settings;

use Spatie\LaravelSettings\Settings;

/**
 * Link with the public website (spec §10). Incoming requests are checked against a SHA-256 hash of
 * the shared key; the key itself is kept encrypted only so this system can call the website back.
 */
class IntegrationSettings extends Settings
{
    public ?string $website_url;

    public ?string $api_key_hash;

    public ?string $outgoing_key;

    public ?string $last_sync_at;

    public ?string $last_sync_status;

    public int $last_sync_count;

    public static function group(): string
    {
        return 'integration';
    }

    /**
     * @return list<string>
     */
    public static function encrypted(): array
    {
        return ['outgoing_key'];
    }
}
