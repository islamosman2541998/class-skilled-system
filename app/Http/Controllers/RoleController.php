<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreRoleRequest;
use App\Http\Requests\UpdateRoleRequest;
use App\Models\Role;
use App\Services\ActivityLogger;
use App\Support\PermissionRegistry;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\View\View;

class RoleController extends Controller
{
    public function index(): View
    {
        Gate::authorize('roles.view');

        return view('roles.index');
    }

    public function create(): View
    {
        Gate::authorize('roles.create');

        return $this->form(new Role(['scope' => Role::SCOPE_ALL]));
    }

    public function store(StoreRoleRequest $request): RedirectResponse
    {
        PermissionRegistry::sync();

        $role = new Role;
        $role->forceFill([
            'name' => $this->uniqueName($request->validated('label_en')),
            'guard_name' => 'web',
            'label_ar' => $request->validated('label_ar'),
            'label_en' => $request->validated('label_en'),
            'scope' => $request->validated('scope'),
            'is_system' => false,
        ])->save();

        $this->syncPermissions($role, $request->validated('permissions', []));

        return redirect()->route('roles.index')->with('success', __('app.saved'));
    }

    public function edit(Role $role): View
    {
        Gate::authorize('roles.update');

        return $this->form($role);
    }

    public function update(UpdateRoleRequest $request, Role $role): RedirectResponse
    {
        PermissionRegistry::sync();

        $role->forceFill([
            'label_ar' => $request->validated('label_ar'),
            'label_en' => $request->validated('label_en'),
        ]);

        // The system role keeps its scope and has every permission through Gate::before.
        if (! $role->is_system) {
            $role->scope = $request->validated('scope');
        }

        $role->save();

        if (! $role->is_system) {
            $this->syncPermissions($role, $request->validated('permissions', []));
        }

        return redirect()->route('roles.index')->with('success', __('app.saved'));
    }

    /**
     * @param  list<string>  $permissions
     */
    private function syncPermissions(Role $role, array $permissions): void
    {
        $before = $role->permissions()->pluck('name')->sort()->values()->all();
        $after = collect($permissions)->sort()->values()->all();

        $role->syncPermissions($permissions);

        if ($before !== $after) {
            ActivityLogger::record('permissions_changed', $role, ['old' => $before, 'attributes' => $after], 'roles');
        }
    }

    private function form(Role $role): View
    {
        return view('roles.form', [
            'role' => $role,
            'modules' => PermissionRegistry::modules(),
            'granted' => $role->exists ? $role->permissions->pluck('name')->all() : [],
        ]);
    }

    private function uniqueName(?string $labelEn): string
    {
        $base = Str::slug((string) $labelEn) ?: 'role';
        $name = $base;
        $suffix = 2;

        while ($name === 'role' || Role::query()->where('name', $name)->exists()) {
            $name = $base.'-'.($base === 'role' ? Str::lower(Str::random(6)) : $suffix++);
        }

        return $name;
    }
}
