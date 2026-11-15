<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    /** Weekly days off for "days without a daily report" (ISO weekdays; 5 = Friday). */
    public function up(): void
    {
        $this->migrator->add('system.weekly_days_off', [5]);
    }
};
