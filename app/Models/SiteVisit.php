<?php

namespace App\Models;

use App\Enums\SiteVisitStatus;
use App\Models\Concerns\HasAttachments;
use App\Models\Concerns\HasRecordScope;
use App\Models\Concerns\LogsModelActivity;
use Database\Factories\SiteVisitFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Spatie\MediaLibrary\HasMedia;

/**
 * A site visit before the quotation (spec §8.3). Engineers see only their own visits.
 *
 * @property int $id
 * @property string $number
 * @property int|null $lead_id
 * @property int $client_id
 * @property Carbon $scheduled_at
 * @property int $engineer_id
 * @property string|null $address
 * @property string|null $map_url
 * @property SiteVisitStatus $status
 * @property string|null $notes
 * @property string|null $measurements
 * @property Carbon|null $result_at
 * @property int|null $created_by
 * @property-read Client $client
 * @property-read Lead|null $lead
 * @property-read User $engineer
 */
class SiteVisit extends Model implements HasMedia
{
    /** @use HasFactory<SiteVisitFactory> */
    use HasAttachments, HasFactory, HasRecordScope, LogsModelActivity, SoftDeletes;

    protected $fillable = [
        'lead_id', 'client_id', 'scheduled_at', 'engineer_id', 'address', 'map_url', 'status',
        'notes', 'measurements', 'result_at', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'scheduled_at' => 'datetime',
            'result_at' => 'datetime',
            'status' => SiteVisitStatus::class,
        ];
    }

    protected static function assigneeColumn(): string
    {
        return 'engineer_id';
    }

    /** @return BelongsTo<Client, $this> */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /** @return BelongsTo<Lead, $this> */
    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    /** @return BelongsTo<User, $this> */
    public function engineer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'engineer_id');
    }

    public function attachmentPermission(): string
    {
        return 'site_visits';
    }
}
