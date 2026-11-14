<?php

namespace App\Http\Requests\Settings;

use App\Enums\NotificationType;
use Closure;
use Illuminate\Validation\Rule;

class NotificationSettingsRequest extends SettingsRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $types = array_map(fn (NotificationType $type) => $type->value, NotificationType::cases());

        return [
            'rules' => ['required', 'array', function (string $attribute, mixed $value, Closure $fail) use ($types) {
                if (array_diff(array_keys((array) $value), $types) !== []) {
                    $fail(__('validation.in', ['attribute' => $attribute]));
                }
            }],
            'rules.*' => ['array'],
            'rules.*.enabled' => ['nullable', 'boolean'],
            'rules.*.days' => ['nullable', 'integer', 'between:1,365'],
            'rules.*.roles' => ['nullable', 'array'],
            'rules.*.roles.*' => ['string', Rule::exists('roles', 'name')],
        ];
    }

    /**
     * The posted rules in the stored shape. Types that were not posted keep their defaults.
     *
     * @return array<string, array{enabled: bool, days: ?int, roles: list<string>}>
     */
    public function values(): array
    {
        $input = (array) $this->validated('rules', []);
        $rules = [];

        foreach (NotificationType::cases() as $type) {
            if (! isset($input[$type->value])) {
                continue;
            }

            $row = (array) $input[$type->value];
            $rules[$type->value] = [
                'enabled' => filter_var($row['enabled'] ?? false, FILTER_VALIDATE_BOOL),
                'days' => $type->usesDays() ? (int) ($row['days'] ?? $type->defaultDays()) : null,
                'roles' => array_values(array_map('strval', (array) ($row['roles'] ?? []))),
            ];
        }

        return $rules;
    }
}
