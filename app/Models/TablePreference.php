<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Per-user display choices for one DataTable.
 *
 * @property int $id
 * @property int $user_id
 * @property string $table_key
 * @property list<string>|null $hidden_columns
 * @property int|null $per_page
 */
class TablePreference extends Model
{
    protected $fillable = ['user_id', 'table_key', 'hidden_columns', 'per_page'];

    protected function casts(): array
    {
        return [
            'hidden_columns' => 'array',
            'per_page' => 'integer',
        ];
    }
}
