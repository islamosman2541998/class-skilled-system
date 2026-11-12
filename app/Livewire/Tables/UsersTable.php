<?php

namespace App\Livewire\Tables;

use App\Livewire\DataTable;
use App\Models\Role;
use App\Models\User;
use App\Tables\Column;
use App\Tables\Filters\BooleanFilter;
use App\Tables\Filters\SelectFilter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class UsersTable extends DataTable
{
    public function tableKey(): string
    {
        return 'users';
    }

    protected function permissionPrefix(): ?string
    {
        return 'users';
    }

    protected function query(): Builder
    {
        return User::query()->with('roles');
    }

    protected function columns(): array
    {
        return [
            Column::make('name', 'app.full_name')->sortable()->onCard(),
            Column::make('email', 'app.email')->sortable()->onCard(),
            Column::make('phone', 'app.phone'),
            Column::make('roles', 'users.roles')->format(fn (User $user) => $user->roles->map(fn (Role $role) => $role->label)->implode('، '))->onCard(),
            Column::make('is_active', 'users.status')->sortable()->view('users.partials.status')
                ->exportAs(fn (User $user) => $user->is_active ? __('users.active') : __('users.inactive'))->onCard(),
            Column::make('last_login_at', 'users.last_login')->sortable()
                ->format(fn (User $user) => $user->last_login_at ? fmt_datetime($user->last_login_at) : __('users.never')),
            Column::make('created_at', 'table.created_at')->sortable()->format(fn (User $user) => fmt_date($user->created_at))->hiddenByDefault(),
        ];
    }

    protected function filterDefinitions(): array
    {
        return [
            SelectFilter::make('role', 'users.role')
                ->options(fn () => Role::query()->orderBy('id')->get()->mapWithKeys(fn (Role $role) => [$role->name => $role->label]))
                ->query(fn (Builder $query, string $value) => $query->whereHas('roles', fn (Builder $roles) => $roles->where('name', $value))),
            BooleanFilter::make('is_active', 'users.status'),
        ];
    }

    protected function searchColumns(): array
    {
        return ['name', 'email', 'phone'];
    }

    protected function editUrl(Model $row): ?string
    {
        return auth()->user()?->can('users.update') ? route('users.edit', $row) : null;
    }
}
