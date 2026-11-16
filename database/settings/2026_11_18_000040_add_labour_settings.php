<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    /** Labour cost of attendance (spec §8.17): working hours a day and the overtime multiplier. */
    public function up(): void
    {
        $this->migrator->add('finance.labour_hours_per_day', 8);
        $this->migrator->add('finance.overtime_multiplier', '1.25');
    }
};
