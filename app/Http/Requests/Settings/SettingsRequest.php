<?php

namespace App\Http\Requests\Settings;

use Illuminate\Foundation\Http\FormRequest;

abstract class SettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('settings.manage');
    }

    /**
     * The validated values exactly as they are stored on the settings class.
     *
     * @return array<string, mixed>
     */
    public function values(): array
    {
        return $this->validated();
    }

    /**
     * Turns "jpg, PNG ,webp" into ['jpg', 'png', 'webp'].
     *
     * @return list<string>
     */
    protected function splitList(mixed $value): array
    {
        if (is_array($value)) {
            $value = implode(',', $value);
        }

        return array_values(array_unique(array_filter(array_map(
            fn (string $item) => strtolower(trim($item, " .\t\n\r")),
            preg_split('/[\s,،]+/u', (string) $value) ?: [],
        ))));
    }
}
