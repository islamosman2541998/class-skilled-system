<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    /** Default retention copied into new projects (spec §5.9). */
    public function up(): void
    {
        $this->migrator->add('finance.retention_percent', '5.00');
    }
};
