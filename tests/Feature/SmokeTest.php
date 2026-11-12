<?php

it('boots in Arabic', function () {
    expect(config('app.locale'))->toBe('ar')
        ->and(config('app.fallback_locale'))->toBe('ar');
});

it('uses the sync queue', function () {
    expect(config('queue.default'))->toBe('sync');
});
