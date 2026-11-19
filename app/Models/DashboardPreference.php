<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Per-user choices for the charts shown on the home dashboard.
 *
 * @property int $user_id
 * @property list<string>|null $hidden_widgets
 */
class DashboardPreference extends Model
{
    protected $fillable = ['user_id', 'hidden_widgets'];

    protected function casts(): array
    {
        return ['hidden_widgets' => 'array'];
    }
}
