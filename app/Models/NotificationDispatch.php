<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $type
 * @property string $subject_type
 * @property int $subject_id
 * @property string $dedupe_key
 */
class NotificationDispatch extends Model
{
    protected $fillable = ['type', 'subject_type', 'subject_id', 'dedupe_key'];
}
