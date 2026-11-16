<?php

use App\Enums\BackupStatus;
use App\Enums\BackupType;
use App\Models\Backup;
use App\Models\User;
use App\Services\Backup\BackupRunner;
use App\Services\Backup\SqlDumper;
use App\Services\DueTasks;
use App\Settings\SystemSettings;
use Database\Seeders\RolesSeeder;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Carbon::setTestNow('2026-11-16 10:00:00');
    Storage::fake('private');
    $this->seed(RolesSeeder::class);
});

it('dumps every table into a gzip file and records a successful backup', function () {
    $backup = app(BackupRunner::class)->run(BackupType::Database);

    expect($backup->status)->toBe(BackupStatus::Success)
        ->and($backup->path)->toBe('backups/db-20261116-100000.sql.gz')
        ->and($backup->size)->toBeGreaterThan(100)
        ->and($backup->finished_at)->not->toBeNull();

    $sql = gzdecode(Storage::disk('private')->get($backup->path));
    expect($sql)->toContain('CREATE TABLE')->toContain('"users"')->toContain('INSERT INTO "roles"');
});

it('the dump restores rows with quotes, Arabic text, NULLs and newlines', function () {
    Schema::create('backup_probe', function (Blueprint $table) {
        $table->id();
        $table->string('name')->nullable();
        $table->text('body')->nullable();
        $table->decimal('amount', 14, 2)->nullable();
    });
    $rows = [
        ['id' => 1, 'name' => "مؤسسة الخليج \"الحديثة\" O'Brien", 'body' => "سطر أول\nسطر ثان\\ وشرطة", 'amount' => '1250.50'],
        ['id' => 2, 'name' => null, 'body' => '', 'amount' => null],
    ];
    DB::table('backup_probe')->insert($rows);
    $path = Storage::disk('private')->path('backups/probe.sql.gz');
    @mkdir(dirname($path), 0775, true);

    app(SqlDumper::class)->dump($path, ['backup_probe']);
    DB::table('backup_probe')->delete();
    DB::unprepared(gzdecode((string) file_get_contents($path)));

    expect(DB::table('backup_probe')->orderBy('id')->get()->map(fn ($row) => (array) $row)->map(fn (array $row) => [...$row, 'amount' => $row['amount'] === null ? null : number_format((float) $row['amount'], 2, '.', '')])->all())
        ->toBe($rows);
});

it('a second run while one is running is refused', function () {
    $lock = Cache::lock('backup:database', 60);
    $lock->get();

    expect(fn () => app(BackupRunner::class)->run(BackupType::Database))->toThrow(DomainException::class, __('settings.backups.running'));

    $lock->release();
    expect(Backup::query()->count())->toBe(0);
});

it('a failed dump records the error and notifies super-admins', function () {
    $admin = User::factory()->create()->assignRole('super-admin');
    $dumper = Mockery::mock(SqlDumper::class);
    $dumper->shouldReceive('dump')->andThrow(new RuntimeException('disk full'));
    app()->instance(SqlDumper::class, $dumper);

    $backup = app(BackupRunner::class)->run(BackupType::Database);

    expect($backup->status)->toBe(BackupStatus::Failed)
        ->and($backup->error)->toBe('disk full')
        ->and($admin->notifications()->count())->toBe(1);
});

it('keeps only the configured number of successful backups', function () {
    $settings = app(SystemSettings::class);
    $settings->backup_keep = 2;
    $settings->save();

    foreach ([1, 2, 3] as $minute) {
        Carbon::setTestNow("2026-11-16 10:0{$minute}:00");
        app(BackupRunner::class)->run(BackupType::Database);
    }

    expect(Backup::query()->pluck('path')->sort()->values()->all())->toBe(['backups/db-20261116-100200.sql.gz', 'backups/db-20261116-100300.sql.gz'])
        ->and(Storage::disk('private')->exists('backups/db-20261116-100100.sql.gz'))->toBeFalse();
});

it('the daily task runs the database backup once a day', function () {
    config(['system.backup_daily' => true]);
    DueTasks::flush();
    DueTasks::registerDefaults();

    app(DueTasks::class)->runDue();
    app(DueTasks::class)->runDue();
    expect(Backup::query()->count())->toBe(1);

    Carbon::setTestNow('2026-11-17 10:05:00');
    app(DueTasks::class)->runDue();
    expect(Backup::query()->count())->toBe(2);
});
