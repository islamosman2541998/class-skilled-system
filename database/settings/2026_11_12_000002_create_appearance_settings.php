<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->add('appearance.color_navy', '#2B4168');
        $this->migrator->add('appearance.color_deep', '#0C122B');
        $this->migrator->add('appearance.color_sky', '#4FC3EE');
        $this->migrator->add('appearance.color_ink', '#24324D');
        $this->migrator->add('appearance.color_muted', '#5D6A80');
        $this->migrator->add('appearance.color_line', '#E3E8EF');
        $this->migrator->add('appearance.color_paper', '#F5F7FA');
        $this->migrator->add('appearance.primary_font', 'cairo');
        $this->migrator->add('appearance.heading_font', 'same');
        $this->migrator->add('appearance.default_locale', 'ar');
    }
};
