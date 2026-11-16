<?php

namespace App\Models;

use App\Enums\BackupStatus;
use App\Enums\BackupType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One backup run (spec §13): its file on the private disk under backups/, size, status and error.
 *
 * @property int $id
 * @property BackupType $type
 * @property BackupStatus $status
 * @property string $path
 * @property int $size
 * @property string|null $error
 * @property Carbon $started_at
 * @property Carbon|null $finished_at
 * @property int|null $created_by
 * @property-read User|null $creator
 */
class Backup extends Model
{
    protected $fillable = ['type', 'status', 'path', 'size', 'error', 'started_at', 'finished_at', 'created_by'];

    protected function casts(): array
    {
        return [
            'type' => BackupType::class,
            'status' => BackupStatus::class,
            'size' => 'integer',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** Only files under backups/ are ever served or deleted. */
    public function hasSafePath(): bool
    {
        return str_starts_with($this->path, 'backups/') && ! str_contains($this->path, '..') && ! str_contains($this->path, chr(92));
    }
}
