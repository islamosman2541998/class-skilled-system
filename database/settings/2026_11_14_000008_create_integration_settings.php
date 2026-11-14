<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->add('integration.website_url', null);
        $this->migrator->add('integration.api_key_hash', null);
        $this->migrator->addEncrypted('integration.outgoing_key', null);
        $this->migrator->add('integration.last_sync_at', null);
        $this->migrator->add('integration.last_sync_status', null);
        $this->migrator->add('integration.last_sync_count', 0);
    }
};
