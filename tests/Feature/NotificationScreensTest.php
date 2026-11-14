<?php

use App\Enums\NotificationType;
use App\Livewire\Tables\NotificationsTable;
use App\Models\User;
use App\Services\Notifier;
use App\Settings\NotificationSettings;
use Database\Seeders\RolesSeeder;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

function notify(User $user, string $url = '/profile'): void
{
    app(Notifier::class)->toUser($user, NotificationType::Assigned, $user, ['number' => 'QT-2026-0001'], $url);
}

it('shows the unread count and clears it on read-all', function () {
    $user = userWith();
    notify($user);
    notify($user);

    actingAs($user)->get('/')->assertOk()->assertSee('data-unread="2"', false);

    actingAs($user)->post(route('notifications.read-all'))->assertRedirect();

    expect($user->unreadNotifications()->count())->toBe(0);
    actingAs($user)->get('/')->assertSee('data-unread="0"', false);
});

it('marks one as read and redirects to its url', function () {
    $user = userWith();
    notify($user, '/profile');
    $notification = $user->notifications()->first();

    actingAs($user)->post(route('notifications.read', $notification->id))->assertRedirect('/profile');

    expect($notification->fresh()->read_at)->not->toBeNull();
});

it('never redirects to another host', function () {
    $user = userWith();
    notify($user, 'https://evil.example/x');

    actingAs($user)->post(route('notifications.read', $user->notifications()->first()->id))->assertRedirect(route('notifications.index'));
});

it('treats a backslash path as another host', function () {
    $user = userWith();
    notify($user, '/\\evil.example/x');

    actingAs($user)->post(route('notifications.read', $user->notifications()->first()->id))->assertRedirect(route('notifications.index'));
});

it('returns 404 for a notification of another user', function () {
    $owner = userWith();
    notify($owner);

    actingAs(userWith())->post(route('notifications.read', $owner->notifications()->first()->id))->assertNotFound();
});

it('lists the latest notifications with translated titles', function () {
    $user = userWith();
    notify($user);

    actingAs($user)->get(route('notifications.latest'))->assertOk()->assertSee(__('notifications.types.assigned', ['number' => 'QT-2026-0001']));
    actingAs($user)->get(route('notifications.index'))->assertOk();
});

it('shows only my notifications in the table and filters by read state', function () {
    $me = userWith();
    notify($me);
    notify($me);
    notify(userWith());
    $me->notifications()->first()->markAsRead();

    actingAs($me);

    expect(Livewire::test(NotificationsTable::class)->viewData('rows')->total())->toBe(2)
        ->and(Livewire::test(NotificationsTable::class)->set('filters.read', '0')->viewData('rows')->total())->toBe(1);
});

it('saves notification rules', function () {
    $this->seed(RolesSeeder::class);

    actingAs(userWith(['settings.manage']))->put(route('settings.notifications.update'), [
        'rules' => [
            'stage_late' => ['enabled' => '0', 'roles' => ['management']],
            'quotation_expiring' => ['enabled' => '1', 'days' => '5', 'roles' => []],
        ],
    ])->assertRedirect();

    $settings = app(NotificationSettings::class);

    expect($settings->rule(NotificationType::StageLate)['enabled'])->toBeFalse()
        ->and($settings->rule(NotificationType::QuotationExpiring)['days'])->toBe(5);
});

it('keeps notification settings behind settings.manage', function () {
    actingAs(userWith())->get(route('settings.notifications'))->assertForbidden();
});
