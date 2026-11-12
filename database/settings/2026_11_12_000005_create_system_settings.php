<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->add('system.trash_retention_days', null);
        $this->migrator->add('system.session_idle_minutes', 120);
        $this->migrator->add('system.public_link_hours', 72);
    }
};
