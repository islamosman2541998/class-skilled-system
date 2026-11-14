<?php

namespace App\Services;

use App\Services\Uploads\TemporaryUpload;
use App\Settings\SystemSettings;
use App\Support\TrashRegistry;
use Closure;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Periodic work without cron (spec §3.2): each task runs on the first request after its interval,
 * under a lock so two requests never run it together. `php artisan app:due-tasks` does the same.
 */
class DueTasks
{
    /** @var array<string, array{every: int, job: Closure}> */
    private static array $tasks = [];

    public static function register(string $name, int $everyMinutes, Closure $job): void
    {
        self::$tasks[$name] = ['every' => $everyMinutes, 'job' => $job];
    }

    /**
     * @return array<string, array{every: int, job: Closure}>
     */
    public static function tasks(): array
    {
        return self::$tasks;
    }

    public static function flush(): void
    {
        self::$tasks = [];
    }

    /** The tasks of Stage 0. Later stages register their own checks in their service providers. */
    public static function registerDefaults(): void
    {
        self::register('purge-trash', 1440, fn () => self::purgeTrash());
        self::register('purge-temp-uploads', 60, fn () => self::purgeTempUploads());
    }

    public function runDue(): void
    {
        foreach (self::$tasks as $name => $task) {
            $lastRun = Cache::get("due:last:{$name}");

            if ($lastRun !== null && now()->getTimestamp() - (int) $lastRun < $task['every'] * 60) {
                continue;
            }

            $lock = Cache::lock("due:{$name}", 300);

            if (! $lock->get()) {
                continue;
            }

            try {
                Cache::forever("due:last:{$name}", now()->getTimestamp());
                ($task['job'])();
            } catch (Throwable $e) {
                Log::error("Due task {$name} failed: {$e->getMessage()}", ['exception' => $e]);
            } finally {
                $lock->release();
            }
        }
    }

    /** Force-deletes records trashed longer than the retention setting; nothing when it is empty. */
    public static function purgeTrash(): void
    {
        $days = app(SystemSettings::class)->trash_retention_days;

        if ($days === null) {
            return;
        }

        $cutoff = now()->subDays($days);

        foreach (TrashRegistry::models() as $model) {
            // Every registered model uses SoftDeletes; the column is named explicitly to keep this plain.
            $model::query()->whereNotNull('deleted_at')->where('deleted_at', '<', $cutoff)->withoutGlobalScopes()->chunkById(200, function ($rows) {
                foreach ($rows as $row) {
                    $row->forceDelete();
                }
            });
        }
    }

    /** Removes transfers that were never attached to a record. */
    public static function purgeTempUploads(): void
    {
        $root = TemporaryUpload::disk()->path(TemporaryUpload::ROOT);

        if (! is_dir($root)) {
            return;
        }

        $cutoff = now()->subDay()->getTimestamp();

        foreach (File::directories($root) as $directory) {
            $upload = TemporaryUpload::find(basename($directory));

            if ($upload === null || $upload->createdAt() < $cutoff) {
                File::deleteDirectory($directory);
            }
        }
    }
}
