<?php

use App\Models\User;
use App\Settings\SystemSettings;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\assertGuest;
use function Pest\Laravel\get;
use function Pest\Laravel\post;

it('redirects guests to login', function () {
    get('/')->assertRedirect(route('login'));
});

it('shows the login page', function () {
    get('/login')->assertOk();
});

it('logs in and stamps last_login_at', function () {
    $user = User::factory()->create(['password' => 'secret-pass-1']);

    post('/login', ['email' => $user->email, 'password' => 'secret-pass-1'])->assertRedirect(route('home'));

    expect($user->fresh()->last_login_at)->not->toBeNull();
});

it('rejects a wrong password', function () {
    $user = User::factory()->create(['password' => 'secret-pass-1']);

    post('/login', ['email' => $user->email, 'password' => 'nope'])->assertSessionHasErrors('email');
    assertGuest();
});

it('rejects an inactive user with auth.inactive', function () {
    $user = User::factory()->create(['password' => 'secret-pass-1', 'is_active' => false]);

    post('/login', ['email' => $user->email, 'password' => 'secret-pass-1'])
        ->assertSessionHasErrors(['email' => __('auth.inactive')]);
    assertGuest();
});

it('throttles the sixth attempt in a minute', function () {
    foreach (range(1, 5) as $attempt) {
        post('/login', ['email' => 'x@y.z', 'password' => 'nope'])->assertSessionHasErrors('email');
    }

    post('/login', ['email' => 'x@y.z', 'password' => 'nope'])->assertStatus(429);
});

it('logs out a user deactivated during the session', function () {
    $user = User::factory()->create();
    actingAs($user)->get('/')->assertOk();

    $user->update(['is_active' => false]);

    get('/')->assertRedirect(route('login'));
    assertGuest();
});

it('logs out after the idle limit', function () {
    $settings = app(SystemSettings::class);
    $settings->session_idle_minutes = 10;
    $settings->save();

    actingAs(User::factory()->create())->get('/')->assertOk();

    $this->travel(11)->minutes();

    get('/')->assertRedirect(route('login'));
    assertGuest();
});

it('stays logged in inside the idle limit', function () {
    actingAs(User::factory()->create())->get('/')->assertOk();

    $this->travel(30)->minutes();

    get('/')->assertOk();
});

it('logs out on request', function () {
    actingAs(User::factory()->create())->post('/logout')->assertRedirect(route('login'));
    assertGuest();
});
