<?php

use App\Enums\BackupStatus;
use App\Enums\BackupType;
use App\Models\Backup;
use App\Services\Backup\BackupRunner;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    Carbon::setTestNow('2026-11-16 10:00:00');
    Storage::fake('private');
    Storage::fake('public');
    $this->admin = scopedUser(['settings.manage']);
});

it('lists backups with the last successful of each type', function () {
    app(BackupRunner::class)->run(BackupType::Database);
    Backup::query()->create(['type' => BackupType::Files, 'status' => BackupStatus::Failed, 'path' => 'backups/files-x.zip', 'error' => 'no space', 'started_at' => now()]);

    actingAs($this->admin)->get(route('settings.backups'))->assertOk()
        ->assertSee('db-20261116-100000.sql.gz')
        ->assertSee('no space')
        ->assertSee(__('settings.backups.never'));
});

it('back up files now archives the private and public disks without the backups folder', function () {
    Storage::disk('private')->put('media/1/plan.pdf', 'PDF');
    Storage::disk('private')->put('backups/old.sql.gz', 'OLD');
    Storage::disk('public')->put('branding/logo.png', 'PNG');

    actingAs($this->admin)->post(route('settings.backups.run', 'files'))->assertRedirect(route('settings.backups'));

    $backup = Backup::query()->where('type', 'files')->sole();
    expect($backup->status)->toBe(BackupStatus::Success);

    $zip = new ZipArchive;
    $zip->open(Storage::disk('private')->path($backup->path));
    $names = collect(range(0, $zip->numFiles - 1))->map(fn (int $i) => $zip->getNameIndex($i))->all();
    $zip->close();

    expect($names)->toContain('private/media/1/plan.pdf')->toContain('public/branding/logo.png')
        ->and(collect($names)->filter(fn (string $name) => str_contains($name, 'backups/'))->all())->toBe([]);
});

it('downloads a backup file and refuses a user without settings.manage', function () {
    $backup = app(BackupRunner::class)->run(BackupType::Database);

    actingAs($this->admin)->get(route('settings.backups.download', $backup))->assertOk()->assertDownload('db-20261116-100000.sql.gz');
    actingAs(scopedUser(['users.view']))->get(route('settings.backups.download', $backup))->assertForbidden();
    actingAs(scopedUser(['users.view']))->get(route('settings.backups'))->assertForbidden();
});

it('deletes a backup and its file', function () {
    $backup = app(BackupRunner::class)->run(BackupType::Database);

    actingAs($this->admin)->delete(route('settings.backups.destroy', $backup))->assertRedirect(route('settings.backups'));

    expect(Backup::query()->count())->toBe(0)->and(Storage::disk('private')->exists($backup->path))->toBeFalse();
});

it('a backup row whose path leaves the backups folder is never served', function () {
    Storage::disk('private')->put('secret.txt', 'SECRET');
    $bad = Backup::query()->create(['type' => BackupType::Database, 'status' => BackupStatus::Success, 'path' => 'backups/../secret.txt', 'started_at' => now()]);
    $outside = Backup::query()->create(['type' => BackupType::Database, 'status' => BackupStatus::Success, 'path' => 'secret.txt', 'started_at' => now()]);

    actingAs($this->admin)->get(route('settings.backups.download', $bad))->assertNotFound();
    actingAs($this->admin)->get(route('settings.backups.download', $outside))->assertNotFound();
    actingAs($this->admin)->delete(route('settings.backups.destroy', $outside));

    expect(Storage::disk('private')->exists('secret.txt'))->toBeTrue();
});
