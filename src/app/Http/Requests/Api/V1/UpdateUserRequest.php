<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUserRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'username' => [
                'sometimes',
                'string',
                'max:50',
                Rule::unique('users')->ignore($this->user),
            ],
            'email' => [
                'sometimes',
                'string',
                'email',
                'max:100',
                Rule::unique('users')->ignore($this->user),
            ],
            'password' => 'sometimes|string|min:8',
            'role' => 'sometimes|in:ADMIN,MANAGER,STAFF',
        ];
    }
}
