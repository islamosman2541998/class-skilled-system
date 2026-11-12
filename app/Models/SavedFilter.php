<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A named set of DataTable filters saved by one user.
 *
 * @property int $id
 * @property int $user_id
 * @property string $table_key
 * @property string $name
 * @property array<string, mixed> $state
 */
class SavedFilter extends Model
{
    protected $fillable = ['user_id', 'table_key', 'name', 'state'];

    protected function casts(): array
    {
        return [
            'state' => 'array',
        ];
    }
}
