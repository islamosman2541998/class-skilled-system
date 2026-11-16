<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    /** Successful backups kept of each type (spec §13). */
    public function up(): void
    {
        $this->migrator->add('system.backup_keep', 7);
    }
};
