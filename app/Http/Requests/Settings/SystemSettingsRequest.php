<?php

namespace App\Http\Requests\Settings;

class SystemSettingsRequest extends SettingsRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'trash_retention_days' => ['nullable', 'integer', 'between:1,3650'],
            'session_idle_minutes' => ['required', 'integer', 'between:5,1440'],
            'public_link_hours' => ['required', 'integer', 'between:1,720'],
            'weekly_days_off' => ['nullable', 'array'],
            'weekly_days_off.*' => ['integer', 'between:1,7', 'distinct'],
        ];
    }

    public function values(): array
    {
        $retention = $this->validated('trash_retention_days');

        return [
            'trash_retention_days' => filled($retention) ? (int) $retention : null,
            'session_idle_minutes' => (int) $this->validated('session_idle_minutes'),
            'public_link_hours' => (int) $this->validated('public_link_hours'),
            'weekly_days_off' => collect($this->validated('weekly_days_off', []))->map(fn ($day) => (int) $day)->unique()->sort()->values()->all(),
        ];
    }
}
