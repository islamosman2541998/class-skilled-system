<?php

namespace App\Support;

/**
 * One comparable form for phone numbers, so 0501234567, +971 50 123 4567 and 00971501234567
 * are recognised as the same number (spec §8.1 duplicate warning).
 */
final class Phone
{
    public static function normalize(?string $phone): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $phone) ?? '';

        if ($digits === '') {
            return null;
        }

        if (str_starts_with($digits, '00')) {
            $digits = substr($digits, 2);
        }

        // A local UAE number: 0 + 9 digits (mobile 05x, landline 02…).
        if (strlen($digits) === 10 && $digits[0] === '0') {
            $digits = '971'.substr($digits, 1);
        }

        return $digits;
    }

    /**
     * Digit forms of a search term to compare with normalized phones: the normalized number, and the
     * digits without leading zeros so a partial local number ("0501234") still matches. Empty when
     * the term is not a phone-like string of at least 5 digits.
     *
     * @return list<string>
     */
    public static function searchVariants(string $term): array
    {
        $digits = preg_replace('/\D+/', '', $term) ?? '';
        $rest = preg_replace('/[\d\s+\-()]/u', '', $term) ?? '';

        if (strlen($digits) < 5 || $rest !== '') {
            return [];
        }

        return array_values(array_unique(array_filter([(string) self::normalize($digits), ltrim($digits, '0')])));
    }
}
