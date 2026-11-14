<?php

namespace App\Http\Requests;

use App\Support\TranslationCatalog;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveTranslationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('translations.manage');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $writing = $this->isMethod('PUT');

        return [
            'group' => ['required', 'string', Rule::in(TranslationCatalog::GROUPS)],
            'key' => ['required', 'string', 'max:190', function (string $attribute, mixed $value, Closure $fail) {
                if (! app(TranslationCatalog::class)->has((string) $this->input('group'), (string) $value)) {
                    $fail(__('validation.exists', ['attribute' => $attribute]));
                }
            }],
            'ar' => [$writing ? 'required' : 'nullable', 'string', 'max:2000'],
            'en' => [$writing ? 'required' : 'nullable', 'string', 'max:2000'],
        ];
    }
}
