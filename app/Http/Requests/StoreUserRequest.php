<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('users.create');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'string', 'email', 'max:190', Rule::unique('users', 'email')->ignore($this->target()?->getKey())],
            'phone' => ['nullable', 'string', 'max:30'],
            'locale' => ['required', 'in:ar,en'],
            'is_active' => ['nullable', 'boolean'],
            'roles' => ['nullable', 'array'],
            'roles.*' => ['string', Rule::exists('roles', 'name')],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ];
    }

    /**
     * @return list<callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                $roles = (array) $this->input('roles', []);
                $target = $this->target();
                $grantsAdmin = in_array('super-admin', $roles, true) && ! $target?->hasRole('super-admin');

                // Only a system administrator may hand out the system administrator role.
                if ($grantsAdmin && ! $this->user()->hasRole('super-admin')) {
                    $validator->errors()->add('roles', __('users.admin_only_role'));
                }

                // The last active administrator keeps the role and stays active.
                if ($target?->isLastActiveSuperAdmin() && (! in_array('super-admin', $roles, true) || ! $this->boolean('is_active', true))) {
                    $validator->errors()->add('roles', __('users.last_admin'));
                }

                if ($target?->is($this->user()) && $this->has('is_active') && ! $this->boolean('is_active')) {
                    $validator->errors()->add('is_active', __('users.cannot_deactivate_self'));
                }
            },
        ];
    }

    protected function target(): ?User
    {
        $user = $this->route('user');

        return $user instanceof User ? $user : null;
    }
}
