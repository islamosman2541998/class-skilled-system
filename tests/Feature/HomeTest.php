<?php

use App\Enums\NotificationType;
use App\Services\Notifier;

use function Pest\Laravel\actingAs;

it('shows my unread notifications and my activity on home', function () {
    $me = userWith();
    $other = userWith();
    app(Notifier::class)->toUser($me, NotificationType::Assigned, $me, ['number' => 'QT-2026-0042'], '/profile');
    app(Notifier::class)->toUser($other, NotificationType::Assigned, $other, ['number' => 'QT-2026-0099'], '/profile');

    actingAs($me);
    $me->update(['name' => 'اسم جديد للتجربة']);

    actingAs($me)->get('/')->assertOk()
        ->assertSee(__('notifications.types.assigned', ['number' => 'QT-2026-0042']))
        ->assertDontSee('QT-2026-0099')
        ->assertSee(__('activity.events.updated'));
});

it('shows empty states when there is nothing yet', function () {
    actingAs(userWith())->get('/')->assertOk()->assertSee(__('notifications.empty'))->assertSee(__('home.no_activity'));
});
