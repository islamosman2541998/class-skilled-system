<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use RuntimeException;

/**
 * Creates the first system administrator from ADMIN_NAME, ADMIN_EMAIL and ADMIN_PASSWORD.
 * Credentials are never written in code; they come from the environment file.
 */
class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        $admin = config('system.admin');

        if (blank($admin['email'] ?? null) || blank($admin['password'] ?? null)) {
            if (app()->environment('testing')) {
                return;
            }

            throw new RuntimeException('Set ADMIN_NAME, ADMIN_EMAIL and ADMIN_PASSWORD in .env before seeding.');
        }

        $user = User::withTrashed()->firstOrNew(['email' => $admin['email']]);

        if (! $user->exists) {
            $user->fill([
                'name' => $admin['name'] ?: $admin['email'],
                'password' => $admin['password'],
                'locale' => 'ar',
                'is_active' => true,
            ])->save();
        }

        $user->assignRole('super-admin');
    }
}
