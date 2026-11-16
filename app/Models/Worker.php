<?php

namespace App\Models;

use App\Models\Concerns\LogsModelActivity;
use Database\Factories\WorkerFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A worker, of the company or external (spec §8.17). The daily wage is copied into each attendance
 * day, so a later change never alters past cost.
 *
 * @property int $id
 * @property string $name_ar
 * @property string|null $name_en
 * @property-read string $name
 * @property int|null $trade_id
 * @property string $daily_wage
 * @property bool $is_company
 * @property string|null $phone
 * @property bool $is_active
 * @property int|null $created_by
 * @property-read Lookup|null $trade
 */
class Worker extends Model
{
    /** @use HasFactory<WorkerFactory> */
    use HasFactory, LogsModelActivity, SoftDeletes;

    protected $fillable = ['name_ar', 'name_en', 'trade_id', 'daily_wage', 'is_company', 'phone', 'is_active'];

    protected function casts(): array
    {
        return [
            'daily_wage' => 'decimal:2',
            'is_company' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function getNameAttribute(): string
    {
        return app()->getLocale() === 'en' && filled($this->name_en) ? (string) $this->name_en : $this->name_ar;
    }

    /** @return BelongsTo<Lookup, $this> */
    public function trade(): BelongsTo
    {
        return $this->belongsTo(Lookup::class, 'trade_id');
    }

    /** @return HasMany<Attendance, $this> */
    public function attendances(): HasMany
    {
        return $this->hasMany(Attendance::class);
    }
}
