<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        // Only overrides are stored; NotificationType holds the defaults.
        $this->migrator->add('notifications.rules', []);
    }
};
