<?php

namespace App\Rules;

use App\Models\Client;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * The client must exist and be visible to the signed-in user (spec §6). Without it an
 * "assigned only" user could link a hidden client to their own record and so reveal it.
 */
class VisibleClient implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $user = auth()->user();

        if ($user === null || ! Client::query()->visibleTo($user)->whereKey($value)->exists()) {
            $fail(__('validation.exists', ['attribute' => $attribute]));
        }
    }
}
