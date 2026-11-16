<?php

use App\Models\DocumentSequence;
use App\Models\Lookup;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

it('install runs migrations and seeds once and keeps an existing admin password', function () {
    config(['system.admin' => ['name' => 'مدير النظام', 'email' => 'admin@example.test', 'password' => 'first-pass-123']]);

    $this->artisan('app:install', ['--no-cache' => true])->assertSuccessful();

    $admin = User::query()->where('email', 'admin@example.test')->sole();
    expect($admin->hasRole('super-admin'))->toBeTrue()
        ->and(Role::query()->where('name', 'engineer')->exists())->toBeTrue()
        ->and(Lookup::query()->count())->toBeGreaterThan(10)
        ->and(DocumentSequence::query()->count())->toBeGreaterThan(5);

    $lookups = Lookup::query()->count();
    config(['system.admin' => ['name' => 'X', 'email' => 'admin@example.test', 'password' => 'second-pass-456']]);
    $this->artisan('app:install', ['--no-cache' => true])->assertSuccessful();

    expect(User::query()->where('email', 'admin@example.test')->count())->toBe(1)
        ->and(Hash::check('first-pass-123', $admin->fresh()->password))->toBeTrue()
        ->and(Lookup::query()->count())->toBe($lookups);
});

it('install refuses without an app key', function () {
    config(['app.key' => '']);

    $this->artisan('app:install', ['--no-cache' => true])->assertFailed();
});
