<?php

use App\Support\Phone;

it('normalizes UAE phone formats to one value', function (string $raw) {
    expect(Phone::normalize($raw))->toBe('971501234567');
})->with(['0501234567', '+971 50 123 4567', '00971501234567', '971-50-123-4567']);

it('returns null for an empty phone', fn () => expect(Phone::normalize('  '))->toBeNull()->and(Phone::normalize(null))->toBeNull());

it('keeps a foreign number as digits', fn () => expect(Phone::normalize('+20 100 123 4567'))->toBe('201001234567'));
