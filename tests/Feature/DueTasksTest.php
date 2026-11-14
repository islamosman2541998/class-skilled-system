<?php

use App\Models\Lookup;
use App\Services\DueTasks;
use App\Services\Uploads\TemporaryUpload;
use App\Settings\SystemSettings;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

use function Pest\Laravel\actingAs;

beforeEach(fn () => DueTasks::flush());

it('runs a task once inside its interval and again after it', function () {
    $calls = 0;
    DueTasks::register('count', 60, function () use (&$calls) {
        $calls++;
    });

    app(DueTasks::class)->runDue();
    app(DueTasks::class)->runDue();
    expect($calls)->toBe(1);

    $this->travel(61)->minutes();
    app(DueTasks::class)->runDue();
    expect($calls)->toBe(2);
});

it('keeps going when a task throws', function () {
    Log::spy();
    $ran = false;
    DueTasks::register('broken', 60, fn () => throw new RuntimeException('boom'));
    DueTasks::register('fine', 60, function () use (&$ran) {
        $ran = true;
    });

    app(DueTasks::class)->runDue();

    expect($ran)->toBeTrue();
    Log::shouldHaveReceived('error')->once();
});

it('runs due tasks after an authenticated request', function () {
    $calls = 0;
    DueTasks::register('after-request', 60, function () use (&$calls) {
        $calls++;
    });

    actingAs(userWith())->get('/')->assertOk();

    expect($calls)->toBe(1);
});

it('runs due tasks from the console command', function () {
    $calls = 0;
    DueTasks::register('console', 60, function () use (&$calls) {
        $calls++;
    });

    $this->artisan('app:due-tasks')->assertSuccessful();

    expect($calls)->toBe(1);
});

it('purges trash older than the retention and nothing when retention is null', function () {
    DueTasks::flush();
    DueTasks::registerDefaults();

    $old = Lookup::factory()->create();
    $old->delete();
    $recent = Lookup::factory()->create();

    $this->travel(40)->days();
    $recent->delete();

    app(DueTasks::class)->runDue();
    expect(Lookup::withTrashed()->count())->toBe(2);

    $settings = app(SystemSettings::class);
    $settings->trash_retention_days = 30;
    $settings->save();

    $this->travel(2)->days();
    app(DueTasks::class)->runDue();

    expect(Lookup::withTrashed()->find($old->id))->toBeNull()
        ->and(Lookup::withTrashed()->find($recent->id))->not->toBeNull();
});

it('purges temp uploads older than a day', function () {
    Storage::fake('private');
    DueTasks::flush();
    DueTasks::registerDefaults();

    $stale = TemporaryUpload::create(1, 10, 'a.jpg');
    $this->travel(25)->hours();
    $fresh = TemporaryUpload::create(1, 10, 'b.jpg');

    app(DueTasks::class)->runDue();

    expect(TemporaryUpload::find($stale->id))->toBeNull()
        ->and(TemporaryUpload::find($fresh->id))->not->toBeNull();
});
