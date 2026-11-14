<?php

namespace App\Models;

use App\Enums\FollowUpType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * One entry of a lead's follow-up log: call, message or note (spec §8.2).
 *
 * @property int $id
 * @property int $lead_id
 * @property FollowUpType $type
 * @property string $body
 * @property int|null $user_id
 * @property Carbon $happened_at
 * @property-read User|null $user
 */
class LeadFollowUp extends Model
{
    use SoftDeletes;

    protected $fillable = ['type', 'body', 'user_id', 'happened_at'];

    protected function casts(): array
    {
        return [
            'type' => FollowUpType::class,
            'happened_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
