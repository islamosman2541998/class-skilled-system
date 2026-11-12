<?php

namespace App\Http\Requests;

use App\Support\PermissionRegistry;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreRoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('roles.create');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'label_ar' => ['required', 'string', 'max:80'],
            'label_en' => ['nullable', 'string', 'max:80'],
            'scope' => ['required', 'in:all,assigned'],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['string', Rule::in(PermissionRegistry::all())],
        ];
    }
}
