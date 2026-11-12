<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;

use function Pest\Laravel\actingAs;

it('updates profile name, phone and locale', function () {
    $user = User::factory()->create(['locale' => 'ar']);

    actingAs($user)->put(route('profile.update'), ['name' => 'Sara Ali', 'phone' => '0501234567', 'locale' => 'en'])
        ->assertRedirect(route('profile.edit'));

    expect($user->fresh())->locale->toBe('en')->name->toBe('Sara Ali')->phone->toBe('0501234567');
});

it('rejects an unsupported locale', function () {
    actingAs(User::factory()->create())->put(route('profile.update'), ['name' => 'A', 'locale' => 'fr'])
        ->assertSessionHasErrors('locale');
});

it('requires the current password to change password', function () {
    $user = User::factory()->create(['password' => 'secret-pass-1']);

    actingAs($user)->put(route('profile.password'), [
        'current_password' => 'wrong', 'password' => 'new-secret-9', 'password_confirmation' => 'new-secret-9',
    ])->assertSessionHasErrors('current_password');

    expect(Hash::check('secret-pass-1', $user->fresh()->password))->toBeTrue();
});

it('requires 8 characters for a new password', function () {
    $user = User::factory()->create(['password' => 'secret-pass-1']);

    actingAs($user)->put(route('profile.password'), [
        'current_password' => 'secret-pass-1', 'password' => 'short77', 'password_confirmation' => 'short77',
    ])->assertSessionHasErrors('password');
});

it('changes the password', function () {
    $user = User::factory()->create(['password' => 'secret-pass-1']);

    actingAs($user)->put(route('profile.password'), [
        'current_password' => 'secret-pass-1', 'password' => 'new-secret-9', 'password_confirmation' => 'new-secret-9',
    ])->assertSessionHasNoErrors();

    expect(Hash::check('new-secret-9', $user->fresh()->password))->toBeTrue();
});
