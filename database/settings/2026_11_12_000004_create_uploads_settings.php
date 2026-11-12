<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->add('uploads.image_max_mb', 10);
        $this->migrator->add('uploads.video_max_mb', 200);
        $this->migrator->add('uploads.file_max_mb', 25);
        $this->migrator->add('uploads.image_types', ['jpg', 'jpeg', 'png', 'webp']);
        $this->migrator->add('uploads.video_types', ['mp4', 'webm']);
        $this->migrator->add('uploads.file_types', ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'dwg', 'zip']);
        $this->migrator->add('uploads.image_max_dimension', 2000);
        $this->migrator->add('uploads.chunk_size_mb', 2);
    }
};
