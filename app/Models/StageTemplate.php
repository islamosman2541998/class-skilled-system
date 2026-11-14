<?php

namespace App\Models;

use App\Models\Concerns\HasLocalizedName;
use App\Models\Concerns\LogsModelActivity;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * One stage of a service's default project plan (spec §7 "قوالب المراحل").
 *
 * @property int $id
 * @property int $service_id
 * @property string $name_ar
 * @property string|null $name_en
 * @property-read string $name
 * @property string $weight
 * @property int $sort_order
 */
class StageTemplate extends Model
{
    use HasLocalizedName, LogsModelActivity, SoftDeletes;

    protected $fillable = ['service_id', 'name_ar', 'name_en', 'weight', 'sort_order'];

    protected function casts(): array
    {
        return ['weight' => 'decimal:2'];
    }

    /**
     * @return Collection<int, StageTemplate>
     */
    public static function forService(?int $serviceId): Collection
    {
        if ($serviceId === null) {
            return new Collection;
        }

        return self::query()->where('service_id', $serviceId)->orderBy('sort_order')->orderBy('id')->get();
    }
}
