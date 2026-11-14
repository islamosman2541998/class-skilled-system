<?php

use App\Enums\LookupType;
use App\Livewire\Tables\ActivityTable;
use App\Livewire\Tables\UsersTable;
use App\Models\Lookup;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolesSeeder;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\post;

function lastActivity(string $event, ?string $log = null): ?Activity
{
    return Activity::query()->where('event', $event)->when($log, fn ($query) => $query->where('log_name', $log))->latest('id')->first();
}

it('logs only the changed attributes with old and new values', function () {
    $actor = userWith();
    $user = User::factory()->create(['name' => 'قبل', 'email' => 'same@example.test']);

    actingAs($actor);
    $user->update(['name' => 'بعد']);

    $activity = lastActivity('updated', 'users');

    expect($activity->subject_id)->toBe($user->id)
        ->and($activity->causer_id)->toBe($actor->id)
        ->and($activity->properties['old'])->toBe(['name' => 'قبل'])
        ->and($activity->properties['attributes'])->toBe(['name' => 'بعد']);
});

it('never logs the password', function () {
    $user = User::factory()->create();
    $user->update(['password' => 'a-brand-new-secret']);

    $logged = Activity::all()->map(fn (Activity $activity) => json_encode($activity->properties))->implode(' ');

    expect($logged)->not->toContain('password')->not->toContain('remember_token');
});

it('logs nothing when a save changes nothing', function () {
    $user = User::factory()->create();
    $before = Activity::count();

    $user->update(['name' => $user->name]);

    expect(Activity::count())->toBe($before);
});

it('logs creation and deletion of a list item', function () {
    actingAs(userWith());
    $item = Lookup::factory()->create(['type' => LookupType::Unit, 'name_ar' => 'متر']);
    $item->delete();

    expect(lastActivity('created', 'lookups')->properties['attributes']['name_ar'])->toBe('متر')
        ->and(lastActivity('deleted', 'lookups')->subject_id)->toBe($item->id);
});

it('logs login and logout', function () {
    $user = User::factory()->create(['password' => 'secret-pass-1']);

    post('/login', ['email' => $user->email, 'password' => 'secret-pass-1']);
    expect(lastActivity('login', 'auth')->causer_id)->toBe($user->id);

    post('/logout');
    expect(lastActivity('logout', 'auth')->causer_id)->toBe($user->id);
});

it('logs a settings update with the changed keys only', function () {
    actingAs(userWith(['settings.manage']))->put(route('settings.update', 'system'), [
        'trash_retention_days' => 30, 'session_idle_minutes' => 120, 'public_link_hours' => 72,
    ]);

    $activity = lastActivity('settings_updated', 'settings');

    expect($activity->properties['group'])->toBe('system')
        ->and($activity->properties['old'])->toBe(['trash_retention_days' => null])
        ->and($activity->properties['attributes'])->toBe(['trash_retention_days' => 30]);
});

it('logs role permission changes', function () {
    $this->seed(RolesSeeder::class);
    $role = Role::findByName('sales');
    $before = $role->permissions->pluck('name')->all();

    actingAs(userWith(['roles.view', 'roles.update']))->put(route('roles.update', $role), [
        'label_ar' => 'مبيعات', 'scope' => 'all', 'permissions' => ['users.view'],
    ]);

    $activity = lastActivity('permissions_changed', 'roles');

    expect($activity->subject_id)->toBe($role->id)
        ->and($activity->properties['attributes'])->toBe(['users.view'])
        ->and($activity->properties['old'])->toEqualCanonicalizing($before);
});

it('logs an export with the table key', function () {
    actingAs(userWith(['users.view', 'users.export']));

    Livewire::test(UsersTable::class)->call('export');

    expect(lastActivity('exported', 'users')->properties['table'])->toBe('users');
});

it('returns 403 on the activity page without activity.view', function () {
    actingAs(userWith())->get(route('activity.index'))->assertForbidden();
    actingAs(userWith(['activity.view']))->get(route('activity.index'))->assertOk();
});

it('offers no way to delete activity', function () {
    $this->seed(RolesSeeder::class);
    $admin = User::factory()->create()->assignRole('super-admin');
    User::factory()->create()->update(['name' => 'تغيير']);
    $activity = Activity::first();

    actingAs($admin);
    Livewire::test(ActivityTable::class)->call('deleteOne', $activity->id)->assertForbidden();
    Livewire::test(ActivityTable::class)->call('deleteAll')->assertForbidden();

    expect(Activity::whereKey($activity->id)->exists())->toBeTrue();
});

it('filters activity by user, module and event', function () {
    $first = userWith(['activity.view']);
    $second = userWith();

    actingAs($first);
    Lookup::factory()->create();
    actingAs($second);
    User::factory()->create()->update(['name' => 'x']);

    actingAs($first);
    $count = fn (array $filters) => Livewire::test(ActivityTable::class)->set('filters', $filters)->viewData('rows')->total();

    expect($count(['causer_id' => (string) $first->id, 'log_name' => 'lookups']))->toBe(1)
        ->and($count(['log_name' => 'users', 'event' => 'updated']))->toBe(1)
        ->and($count(['log_name' => 'nothing']))->toBe(0);
});

it('scopes the tab to one record', function () {
    actingAs(userWith(['activity.view']));
    $one = Lookup::factory()->create(['name_ar' => 'أول']);
    $two = Lookup::factory()->create(['name_ar' => 'ثان']);
    $one->update(['name_ar' => 'أول معدل']);

    $rows = Livewire::test(ActivityTable::class, ['subjectType' => $one->getMorphClass(), 'subjectId' => $one->id])->viewData('rows');

    expect($rows->total())->toBe(2)
        ->and(collect($rows->items())->pluck('subject_id')->unique()->all())->toBe([$one->id]);
});
