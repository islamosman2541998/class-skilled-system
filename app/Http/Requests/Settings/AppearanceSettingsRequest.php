<?php

namespace App\Http\Requests\Settings;

class AppearanceSettingsRequest extends SettingsRequest
{
    public const COLORS = ['color_navy', 'color_deep', 'color_sky', 'color_ink', 'color_muted', 'color_line', 'color_paper'];

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $rules = [
            'primary_font' => ['required', 'in:cairo,tajawal'],
            'heading_font' => ['required', 'in:same,other'],
            'default_locale' => ['required', 'in:ar,en'],
        ];

        foreach (self::COLORS as $color) {
            // Printed inside a <style> block, so only a strict hex colour is accepted.
            $rules[$color] = ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'];
        }

        return $rules;
    }

    public function values(): array
    {
        $values = $this->validated();

        foreach (self::COLORS as $color) {
            $values[$color] = strtoupper($values[$color]);
        }

        return $values;
    }
}
