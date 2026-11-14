<?php

namespace App\Models;

use App\Models\Concerns\LogsModelActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $id
 * @property int $client_id
 * @property string $name
 * @property string|null $position
 * @property string|null $phone
 * @property string|null $email
 */
class ClientContact extends Model
{
    use LogsModelActivity, SoftDeletes;

    protected $fillable = ['name', 'position', 'phone', 'email'];

    /** @return BelongsTo<Client, $this> */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function activityModule(): string
    {
        return 'clients';
    }
}
