<?php

namespace App\Models;

use App\Contracts\GuardsDeletion;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Spatie\Permission\Traits\HasRoles;

/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property string|null $phone
 * @property string $password
 * @property string $locale
 * @property bool $is_active
 * @property Carbon|null $last_login_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 */
class User extends Authenticatable implements GuardsDeletion
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRoles, Notifiable, SoftDeletes;

    protected $fillable = ['name', 'email', 'phone', 'password', 'locale', 'is_active'];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'is_active' => 'boolean',
            'last_login_at' => 'datetime',
        ];
    }

    /**
     * Whether the user sees every record or only the ones assigned to them (spec §6).
     */
    public function seesAllRecords(): bool
    {
        return $this->roles->where('scope', Role::SCOPE_ALL)->isNotEmpty();
    }

    public function deletionBlockedReason(User $by): ?string
    {
        if ($this->is($by)) {
            return __('users.cannot_delete_self');
        }

        return $this->isLastActiveSuperAdmin() ? __('users.last_admin') : null;
    }

    /**
     * True when removing or deactivating this user would leave the system without an administrator.
     */
    public function isLastActiveSuperAdmin(): bool
    {
        if (! $this->is_active || ! $this->hasRole('super-admin')) {
            return false;
        }

        return ! self::query()->role('super-admin')->where('is_active', true)->whereKeyNot($this->getKey())->exists();
    }
}
