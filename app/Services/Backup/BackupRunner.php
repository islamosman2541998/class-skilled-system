<?php

namespace App\Services\Backup;

use App\Enums\BackupStatus;
use App\Enums\BackupType;
use App\Enums\NotificationType;
use App\Models\Backup;
use App\Models\User;
use App\Services\Notifier;
use App\Settings\SystemSettings;
use DomainException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use LogicException;
use Throwable;

/**
 * Runs one backup (spec §13): one at a time per type under a cache lock, logged in `backups`, the
 * administrators notified when it fails, and only the configured number of good backups kept.
 */
class BackupRunner
{
    public function __construct(
        private readonly SqlDumper $dumper,
        private readonly SystemSettings $settings,
        private readonly Notifier $notifier,
    ) {}

    /**
     * @throws DomainException with a translated message when a backup of this type is running
     */
    public function run(BackupType $type, ?User $by = null): Backup
    {
        $lock = Cache::lock('backup:'.$type->value, 3600);

        if (! $lock->get()) {
            throw new DomainException(__('settings.backups.running'));
        }

        try {
            @set_time_limit(0);
            $disk = Storage::disk('private');
            $backup = Backup::query()->create([
                'type' => $type,
                'status' => BackupStatus::Running,
                'path' => 'backups/'.$type->fileName(now()->format('Ymd-His')),
                'started_at' => now(),
                'created_by' => $by?->id,
            ]);

            try {
                $disk->makeDirectory('backups');
                $size = match ($type) {
                    BackupType::Database => $this->dumper->dump($disk->path($backup->path)),
                    BackupType::Files => throw new LogicException('Files backup is not available yet.'),
                };
                $backup->update(['status' => BackupStatus::Success, 'size' => $size, 'finished_at' => now()]);
                $this->prune($type);
            } catch (Throwable $e) {
                Log::error("Backup {$type->value} failed: {$e->getMessage()}", ['exception' => $e]);
                $backup->update(['status' => BackupStatus::Failed, 'error' => mb_substr($e->getMessage(), 0, 2000), 'finished_at' => now()]);
                $disk->delete($backup->path);
                $this->notifier->toRoles(NotificationType::BackupFailed, $backup, ['type' => $type->label()], url('settings/backups'), 'backup-'.$backup->id);
            }

            return $backup;
        } finally {
            $lock->release();
        }
    }

    /** Keeps the newest successful backups of the type; older files and rows go. */
    private function prune(BackupType $type): void
    {
        $keep = max(1, (int) $this->settings->backup_keep);
        $old = Backup::query()->where('type', $type->value)->where('status', BackupStatus::Success->value)
            ->orderByDesc('started_at')->orderByDesc('id')->skip($keep)->take(PHP_INT_MAX)->get();

        foreach ($old as $backup) {
            if ($backup->hasSafePath()) {
                Storage::disk('private')->delete($backup->path);
            }

            $backup->delete();
        }
    }
}
