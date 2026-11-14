<?php

use App\Models\User;

use function Pest\Laravel\actingAs;

it('returns nothing for a one-character term', fn () => actingAs(userWith(['users.view']))->getJson('/search?q=a')->assertExactJson([]));

it('requires a signed-in user', fn () => $this->getJson('/search?q=ab')->assertUnauthorized());

it('groups results by provider', function () {
    $saeed = User::factory()->create(['name' => 'سعيد المنصوري']);

    $groups = actingAs(userWith(['users.view']))->getJson('/search?q='.urlencode('سعيد'))->assertOk()->json();

    expect($groups[0]['key'])->toBe('users')
        ->and($groups[0]['label'])->toBe(__('users.users'))
        ->and($groups[0]['results'][0]['title'])->toBe('سعيد المنصوري')
        ->and($groups[0]['results'][0]['url'])->toBe(route('users.edit', $saeed));
});

it('matches phone and email', function () {
    User::factory()->create(['name' => 'X', 'phone' => '0501234567', 'email' => 'site@example.com']);

    actingAs(userWith(['users.view']))->getJson('/search?q=0501234')->assertJsonPath('0.results.0.title', 'X');
    actingAs(userWith(['users.view']))->getJson('/search?q=site@exa')->assertJsonPath('0.results.0.title', 'X');
});

it('hides a group the user may not view', function () {
    User::factory()->create(['name' => 'سعيد']);

    actingAs(userWith())->getJson('/search?q='.urlencode('سعيد'))->assertExactJson([]);
});

it('matches % literally', function () {
    User::factory()->create(['name' => 'خصم 50% نقدا']);
    User::factory()->create(['name' => 'خصم 500 نقدا']);

    $results = actingAs(userWith(['users.view']))->getJson('/search?q='.urlencode('50%'))->json('0.results');

    expect($results)->toHaveCount(1)->and($results[0]['title'])->toBe('خصم 50% نقدا');
});

it('limits each group to 5', function () {
    User::factory()->count(8)->sequence(fn ($s) => ['name' => 'مهندس '.$s->index])->create();

    expect(actingAs(userWith(['users.view']))->getJson('/search?q='.urlencode('مهندس'))->json('0.results'))->toHaveCount(5);
});
