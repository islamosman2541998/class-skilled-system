<?php

use function Pest\Laravel\actingAs;

it('renders the design pages in both languages without errors', function (string $locale) {
    $user = userWith(['settings.manage', 'lookups.view'], ['locale' => $locale]);

    actingAs($user)->get(route('design.index'))->assertOk()->assertSee('lang="'.$locale.'"', false);
    actingAs($user)->get(route('design.record'))->assertOk()->assertSee('record-header', false);
})->with(['ar', 'en']);

it('keeps design pages behind settings.manage', function () {
    actingAs(userWith())->get(route('design.index'))->assertForbidden();
    actingAs(userWith())->get(route('design.record'))->assertForbidden();
});
