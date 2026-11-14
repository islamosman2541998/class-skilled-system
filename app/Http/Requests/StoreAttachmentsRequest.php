<?php

namespace App\Http\Requests;

use App\Enums\LookupType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Transfer ids from <x-uploader> plus an optional title and category. Authorization is done by the
 * controller of the owning module, which knows the record's permission prefix.
 */
class StoreAttachmentsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'uploads' => ['required', 'array', 'min:1', 'max:50'],
            'uploads.*' => ['required', 'string', 'uuid'],
            'title' => ['nullable', 'string', 'max:190'],
            'category_id' => ['nullable', 'integer', Rule::exists('lookups', 'id')->where('type', LookupType::AttachmentCategory->value)],
        ];
    }

    /**
     * @return array{title: ?string, category_id: ?int}
     */
    public function meta(): array
    {
        return [
            'title' => $this->validated('title'),
            'category_id' => $this->validated('category_id') !== null ? (int) $this->validated('category_id') : null,
        ];
    }
}
