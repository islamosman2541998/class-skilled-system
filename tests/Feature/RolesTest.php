<?php

use App\Livewire\Tables\RolesTable;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolesSeeder;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

const ROLES_ALL = ['roles.view', 'roles.create', 'roles.update', 'roles.delete'];

beforeEach(fn () => $this->seed(RolesSeeder::class));

it('returns 403 on roles pages without roles.view', function () {
    actingAs(userWith())->get(route('roles.index'))->assertForbidden();
});

it('shows the permission matrix built from the registry', function () {
    actingAs(userWith(ROLES_ALL))->get(route('roles.create'))->assertOk()
        ->assertSee('value="users.view"', false)
        ->assertSee('value="settings.manage"', false);
});

it('creates a role and saves exactly the ticked permissions', function () {
    actingAs(userWith(ROLES_ALL))->post(route('roles.store'), [
        'label_ar' => 'مشرف موقع', 'label_en' => 'Site supervisor', 'scope' => 'assigned',
        'permissions' => ['users.view', 'lookups.view'],
    ])->assertRedirect(route('roles.index'));

    $role = Role::where('label_ar', 'مشرف موقع')->first();

    expect($role->name)->toBe('site-supervisor')
        ->and($role->scope)->toBe('assigned')
        ->and($role->permissions->pluck('name')->sort()->values()->all())->toBe(['lookups.view', 'users.view']);
});

it('replaces permissions on update', function () {
    $role = Role::findByName('sales');

    actingAs(userWith(ROLES_ALL))->put(route('roles.update', $role), [
        'label_ar' => 'مبيعات', 'scope' => 'all', 'permissions' => ['users.view'],
    ])->assertRedirect(route('roles.index'));

    expect($role->fresh()->permissions->pluck('name')->all())->toBe(['users.view']);
});

it('rejects an unknown permission and an unknown scope', function () {
    actingAs(userWith(ROLES_ALL))->post(route('roles.store'), [
        'label_ar' => 'x', 'scope' => 'everything', 'permissions' => ['made.up'],
    ])->assertSessionHasErrors(['scope', 'permissions.0']);
});

it('gives roles without an English label a unique name', function () {
    $actor = userWith(ROLES_ALL);

    actingAs($actor)->post(route('roles.store'), ['label_ar' => 'دور أول', 'scope' => 'all']);
    actingAs($actor)->post(route('roles.store'), ['label_ar' => 'دور ثان', 'scope' => 'all']);

    expect(Role::count())->toBe(7)->and(Role::pluck('name')->unique()->count())->toBe(7);
});

it('refuses to delete a system role or a role in use', function () {
    User::factory()->create()->assignRole('sales');

    actingAs(userWith(ROLES_ALL));
    Livewire::test(RolesTable::class)
        ->call('deleteOne', Role::findByName('super-admin')->id)
        ->call('deleteOne', Role::findByName('sales')->id)
        ->call('deleteOne', Role::findByName('accountant')->id);

    expect(Role::pluck('name')->all())->toContain('super-admin', 'sales')->not->toContain('accountant');
});

it('does not let the system role lose its name or flag', function () {
    $admin = Role::findByName('super-admin');

    actingAs(userWith(ROLES_ALL))->put(route('roles.update', $admin), [
        'label_ar' => 'المدير العام', 'scope' => 'assigned', 'permissions' => [],
    ]);

    expect($admin->fresh())->name->toBe('super-admin')->label_ar->toBe('المدير العام')->scope->toBe('all')->is_system->toBeTrue();
});
