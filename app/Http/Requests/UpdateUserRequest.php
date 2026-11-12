<?php

namespace App\Http\Requests;

class UpdateUserRequest extends StoreUserRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('users.update');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $rules = parent::rules();

        // The password is changed from its own form (users.reset-password).
        unset($rules['password']);

        return $rules;
    }
}
