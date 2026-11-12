<?php

use App\Livewire\Tables\UsersTable;
use App\Models\User;
use Database\Seeders\RolesSeeder;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

const USERS_ALL = ['users.view', 'users.create', 'users.update', 'users.delete', 'users.restore', 'users.force-delete', 'users.export'];

beforeEach(fn () => $this->seed(RolesSeeder::class));

it('returns 403 on users pages without users.view', function () {
    actingAs(userWith())->get(route('users.index'))->assertForbidden();
    actingAs(userWith(['users.view']))->get(route('users.create'))->assertForbidden();
});

it('lists users with their role labels', function () {
    User::factory()->create(['name' => 'سعيد الظاهري'])->assignRole('engineer');

    actingAs(userWith(USERS_ALL))->get(route('users.index'))->assertOk()
        ->assertSee('سعيد الظاهري')->assertSee('مهندس مشروع');
});

it('creates a user with roles', function () {
    actingAs(userWith(USERS_ALL))->post(route('users.store'), [
        'name' => 'فاطمة الحمادي', 'email' => 'fatima@example.test', 'phone' => '0501112223', 'locale' => 'ar',
        'is_active' => '1', 'roles' => ['sales', 'accountant'], 'password' => 'first-secret-1', 'password_confirmation' => 'first-secret-1',
    ])->assertRedirect(route('users.index'));

    $user = User::where('email', 'fatima@example.test')->first();

    expect($user)->not->toBeNull()
        ->and($user->getRoleNames()->sort()->values()->all())->toBe(['accountant', 'sales'])
        ->and(Hash::check('first-secret-1', $user->password))->toBeTrue();
});

it('rejects a duplicate email and a short password', function () {
    User::factory()->create(['email' => 'taken@example.test']);

    actingAs(userWith(USERS_ALL))->post(route('users.store'), [
        'name' => 'X', 'email' => 'taken@example.test', 'locale' => 'ar', 'password' => 'short77', 'password_confirmation' => 'short77',
    ])->assertSessionHasErrors(['email', 'password']);
});

it('updates a user without touching the password', function () {
    $user = User::factory()->create(['password' => 'keep-this-one']);

    actingAs(userWith(USERS_ALL))->put(route('users.update', $user), [
        'name' => 'اسم جديد', 'email' => $user->email, 'locale' => 'en', 'is_active' => '1', 'roles' => ['engineer'],
    ])->assertRedirect(route('users.index'));

    expect($user->fresh())->name->toBe('اسم جديد')->locale->toBe('en')
        ->and(Hash::check('keep-this-one', $user->fresh()->password))->toBeTrue()
        ->and($user->fresh()->hasRole('engineer'))->toBeTrue();
});

it('never deletes the acting user', function () {
    $actor = userWith(USERS_ALL);
    User::factory()->count(3)->create();

    actingAs($actor);
    Livewire::test(UsersTable::class)->call('deleteAll');

    expect(User::pluck('id')->all())->toBe([$actor->id]);
});

it('protects the last super-admin from delete and deactivation', function () {
    $admin = User::factory()->create()->assignRole('super-admin');
    $actor = userWith(USERS_ALL);

    actingAs($actor);
    Livewire::test(UsersTable::class)->call('deleteOne', $admin->id);
    expect($admin->fresh()->trashed())->toBeFalse();

    actingAs($actor)->patch(route('users.toggle-active', $admin))->assertSessionHas('error');
    expect($admin->fresh()->is_active)->toBeTrue();

    actingAs($actor)->put(route('users.update', $admin), [
        'name' => $admin->name, 'email' => $admin->email, 'locale' => 'ar', 'roles' => ['sales'],
    ])->assertSessionHasErrors('roles');
    expect($admin->fresh()->hasRole('super-admin'))->toBeTrue();
});

it('allows deleting a super-admin when another active one exists', function () {
    $first = User::factory()->create()->assignRole('super-admin');
    User::factory()->create()->assignRole('super-admin');

    actingAs(userWith(USERS_ALL));
    Livewire::test(UsersTable::class)->call('deleteOne', $first->id);

    expect($first->fresh()->trashed())->toBeTrue();
});

it('toggles another user active state but not your own', function () {
    $actor = userWith(USERS_ALL);
    $other = User::factory()->create();

    actingAs($actor)->patch(route('users.toggle-active', $other));
    expect($other->fresh()->is_active)->toBeFalse();

    actingAs($actor)->patch(route('users.toggle-active', $actor))->assertSessionHas('error');
    expect($actor->fresh()->is_active)->toBeTrue();
});

it('lets an admin reset the password of another user', function () {
    $other = User::factory()->create();

    actingAs(userWith(USERS_ALL))->put(route('users.reset-password', $other), [
        'password' => 'brand-new-pass', 'password_confirmation' => 'brand-new-pass',
    ])->assertSessionHasNoErrors();

    expect(Hash::check('brand-new-pass', $other->fresh()->password))->toBeTrue();
});

it('filters users by role and status', function () {
    actingAs(userWith(USERS_ALL, ['name' => 'المنفذ']));
    User::factory()->create(['name' => 'مهندس'])->assignRole('engineer');
    User::factory()->inactive()->create(['name' => 'موقوف']);

    $names = fn ($table) => collect($table->viewData('rows')->items())->pluck('name')->all();

    expect($names(Livewire::test(UsersTable::class)->set('filters.role', 'engineer')))->toBe(['مهندس'])
        ->and($names(Livewire::test(UsersTable::class)->set('filters.is_active', '0')))->toBe(['موقوف'])
        ->and($names(Livewire::test(UsersTable::class)->set('search', 'مهند')))->toBe(['مهندس']);
});

it('lets only a super-admin grant the super-admin role', function () {
    $target = User::factory()->create();

    actingAs(userWith(USERS_ALL))->put(route('users.update', $target), [
        'name' => $target->name, 'email' => $target->email, 'locale' => 'ar', 'is_active' => '1', 'roles' => ['super-admin'],
    ])->assertSessionHasErrors('roles');
    expect($target->fresh()->hasRole('super-admin'))->toBeFalse();

    $admin = User::factory()->create()->assignRole('super-admin');
    actingAs($admin)->put(route('users.update', $target), [
        'name' => $target->name, 'email' => $target->email, 'locale' => 'ar', 'is_active' => '1', 'roles' => ['super-admin'],
    ])->assertSessionHasNoErrors();
    expect($target->fresh()->hasRole('super-admin'))->toBeTrue();
});
