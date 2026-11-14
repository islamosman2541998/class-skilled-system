<?php

use App\Enums\NotificationType;
use App\Models\User;
use App\Services\Notifier;
use App\Settings\NotificationSettings;
use Database\Seeders\RolesSeeder;

beforeEach(fn () => $this->seed(RolesSeeder::class));

function notifier(): Notifier
{
    return app(Notifier::class);
}

it('notifies active users of the configured roles only', function () {
    $subject = User::factory()->create();
    User::factory()->create()->assignRole('sales');
    User::factory()->create(['is_active' => false])->assignRole('sales');
    User::factory()->create()->assignRole('engineer');

    $sent = notifier()->toRoles(NotificationType::LeadFromWebsite, $subject, ['number' => 'LD-1'], '/x');

    expect($sent)->toBe(1)
        ->and(User::role('sales')->where('is_active', true)->first()->notifications()->count())->toBe(1)
        ->and(User::role('engineer')->first()->notifications()->count())->toBe(0);
});

it('stores the type, title key, params and url', function () {
    $user = User::factory()->create();

    notifier()->toUser($user, NotificationType::Assigned, $user, ['number' => 'QT-1'], '/quotations/1');

    $data = $user->notifications()->first()->data;

    expect($data['type'])->toBe('assigned')
        ->and($data['title_key'])->toBe('notifications.types.assigned')
        ->and($data['params'])->toBe(['number' => 'QT-1'])
        ->and($data['url'])->toBe('/quotations/1')
        ->and($data['subject_id'])->toBe($user->id);
});

it('sends nothing for a disabled type', function () {
    $settings = app(NotificationSettings::class);
    $settings->rules = ['assigned' => ['enabled' => false]];
    $settings->save();
    $user = User::factory()->create();

    expect(notifier()->toUser($user, NotificationType::Assigned, $user, [], '/'))->toBeFalse()
        ->and($user->notifications()->count())->toBe(0);
});

it('skips an inactive user', function () {
    $user = User::factory()->create(['is_active' => false]);

    expect(notifier()->toUser($user, NotificationType::Assigned, $user, [], '/'))->toBeFalse();
});

it('sends once per dedupe key', function () {
    $user = User::factory()->create();

    notifier()->toUser($user, NotificationType::VisitToday, $user, [], '/', 'due-2026-11-12');
    notifier()->toUser($user, NotificationType::VisitToday, $user, [], '/', 'due-2026-11-12');
    notifier()->toUser($user, NotificationType::VisitToday, $user, [], '/', 'due-2026-11-13');

    expect($user->notifications()->count())->toBe(2);
});

it('dedupes a role notification for all recipients together', function () {
    $subject = User::factory()->create();
    User::factory()->count(2)->create()->each->assignRole('sales');

    expect(notifier()->toRoles(NotificationType::LeadFromWebsite, $subject, [], '/', 'k'))->toBe(2)
        ->and(notifier()->toRoles(NotificationType::LeadFromWebsite, $subject, [], '/', 'k'))->toBe(0);
});

it('merges stored rules over the defaults', function () {
    $settings = app(NotificationSettings::class);
    $settings->rules = ['quotation_expiring' => ['days' => 5]];
    $settings->save();

    $rule = app(NotificationSettings::class)->rule(NotificationType::QuotationExpiring);

    expect($rule)->toBe(['enabled' => true, 'days' => 5, 'roles' => []])
        ->and(app(NotificationSettings::class)->rule(NotificationType::InvoiceOverdue)['roles'])->toBe(['accountant', 'management']);
});
