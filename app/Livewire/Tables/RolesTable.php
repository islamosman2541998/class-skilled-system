<?php

namespace App\Livewire\Tables;

use App\Livewire\DataTable;
use App\Models\Role;
use App\Tables\Column;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class RolesTable extends DataTable
{
    public function tableKey(): string
    {
        return 'roles';
    }

    protected function permissionPrefix(): ?string
    {
        return 'roles';
    }

    protected function query(): Builder
    {
        return Role::query()->withCount(['users', 'permissions']);
    }

    protected function columns(): array
    {
        return [
            Column::make('label', 'users.role')->sortable('label_ar')->format(fn (Role $role) => $role->label)->onCard(),
            Column::make('scope', 'users.scope')->sortable()
                ->format(fn (Role $role) => $role->scope === Role::SCOPE_ASSIGNED ? __('users.scope_assigned') : __('users.scope_all'))->onCard(),
            Column::make('users_count', 'users.users_count')->sortable()->alignEnd()->onCard(),
            Column::make('permissions_count', 'users.permissions')->sortable()->alignEnd()
                ->format(fn (Role $role) => $role->is_system ? __('table.all') : (string) $role->permissions_count),
        ];
    }

    protected function searchColumns(): array
    {
        return ['label_ar', 'label_en', 'name'];
    }

    protected function dateColumn(): ?string
    {
        return null;
    }

    protected function defaultSort(): array
    {
        return ['id', 'asc'];
    }

    protected function editUrl(Model $row): ?string
    {
        return auth()->user()?->can('roles.update') ? route('roles.edit', $row) : null;
    }
}
