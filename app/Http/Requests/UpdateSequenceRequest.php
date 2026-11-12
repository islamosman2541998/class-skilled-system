<?php

namespace App\Http\Requests;

use App\Enums\DocumentType;
use App\Models\DocumentSequence;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateSequenceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('settings.manage');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $rules = ['sequences' => ['required', 'array']];

        foreach (DocumentType::cases() as $type) {
            $key = 'sequences.'.$type->value;

            $rules[$key] = ['required', 'array'];
            $rules[$key.'.prefix'] = ['required', 'string', 'regex:/^[A-Za-z0-9-]{1,10}$/'];
            $rules[$key.'.padding'] = ['required', 'integer', 'between:3,8'];
            $rules[$key.'.next_number'] = ['required', 'integer', 'min:1'];
            $rules[$key.'.include_year'] = ['nullable', 'boolean'];
            $rules[$key.'.reset_yearly'] = ['nullable', 'boolean'];
        }

        return $rules;
    }

    /**
     * @return list<callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                // The counter may only move forward, otherwise issued numbers would repeat.
                foreach (DocumentSequence::all() as $sequence) {
                    $submitted = $this->input("sequences.{$sequence->type->value}.next_number");

                    if (is_numeric($submitted) && (int) $submitted < $sequence->next_number) {
                        $validator->errors()->add(
                            "sequences.{$sequence->type->value}.next_number",
                            __('settings.numbering.cannot_decrease', ['min' => $sequence->next_number]),
                        );
                    }
                }
            },
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        $attributes = [];

        foreach (DocumentType::cases() as $type) {
            $attributes["sequences.{$type->value}.prefix"] = __('settings.numbering.prefix');
            $attributes["sequences.{$type->value}.padding"] = __('settings.numbering.padding');
            $attributes["sequences.{$type->value}.next_number"] = __('settings.numbering.next_number');
        }

        return $attributes;
    }
}
