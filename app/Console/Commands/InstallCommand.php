<?php

namespace App\Console\Commands;

use Database\Seeders\AdminUserSeeder;
use Database\Seeders\DocumentSequenceSeeder;
use Database\Seeders\LookupSeeder;
use Database\Seeders\RolesSeeder;
use Illuminate\Console\Command;
use Throwable;

/**
 * First run on the hosting (spec §15 Stage 6): checks PHP and its extensions, migrates, seeds the
 * roles, lists, document numbers and the first administrator, links storage and caches config,
 * routes and views. Every step can run again: nothing is duplicated and no password is replaced.
 */
class InstallCommand extends Command
{
    protected $signature = 'app:install {--no-cache : Skip caching config, routes and views}';

    protected $description = 'Install or update the system on the server';

    private const EXTENSIONS = ['bcmath', 'gd', 'intl', 'mbstring', 'pdo_mysql', 'zip', 'zlib', 'fileinfo', 'openssl'];

    public function handle(): int
    {
        if (version_compare(PHP_VERSION, '8.2.0', '<')) {
            $this->error('PHP 8.2 or newer is required; this server runs '.PHP_VERSION.'.');

            return self::FAILURE;
        }

        $missing = array_values(array_filter(self::EXTENSIONS, fn (string $extension) => ! extension_loaded($extension)));

        if ($missing !== []) {
            $this->error('Missing PHP extensions: '.implode(', ', $missing).'.');

            return self::FAILURE;
        }

        if (blank(config('app.key'))) {
            $this->error('APP_KEY is empty. Run "php artisan key:generate" first.');

            return self::FAILURE;
        }

        $this->step('Database tables', fn () => $this->call('migrate', ['--force' => true]));
        $this->step('Roles and permissions', fn () => $this->call('db:seed', ['--class' => RolesSeeder::class, '--force' => true]));
        $this->step('Lists', fn () => $this->call('db:seed', ['--class' => LookupSeeder::class, '--force' => true]));
        $this->step('Document numbers', fn () => $this->call('db:seed', ['--class' => DocumentSequenceSeeder::class, '--force' => true]));
        $this->step('First administrator', fn () => $this->call('db:seed', ['--class' => AdminUserSeeder::class, '--force' => true]));

        if (! file_exists(public_path('storage'))) {
            $this->step('Public storage link', function () {
                try {
                    $this->call('storage:link');
                } catch (Throwable $e) {
                    $this->warn('Could not link public/storage ('.$e->getMessage().'). See docs/deploy/shared-hosting.md.');
                }
            });
        }

        if (! $this->option('no-cache')) {
            $this->step('Caches', function () {
                $this->call('config:cache');
                $this->call('route:cache');
                $this->call('view:cache');
            });
        }

        $this->info('Done.');

        return self::SUCCESS;
    }

    private function step(string $label, callable $run): void
    {
        $this->line('> '.$label);
        $run();
    }
}
