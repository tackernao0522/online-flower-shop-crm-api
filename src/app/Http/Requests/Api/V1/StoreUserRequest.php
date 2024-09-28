<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Log;

class StoreUserRequest extends FormRequest
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
            'username' => 'required|string|max:50|unique:users',
            'email' => 'required|string|email|max:100|unique:users',
            'password' => 'required|string|min:8',
            'role' => 'required|in:ADMIN,MANAGER,STAFF',
            'is_active' => 'boolean',
        ];
    }

    public function messages()
    {
        Log::info('StoreUserRequest validation rules:', $this->all());
        return [
            'username.required' => 'ユーザー名は必須です。',
            'username.unique' => 'このユーザー名は既に使用されています。',
            'email.unique' => 'このメールアドレスは既に登録されています。',
            'password.min' => 'パスワードは8文字以上である必要があります。',
            'role.in' => '選択された役割は無効です。',
            'is_active.boolean' => 'ステータスは真偽値である必要があります。',
        ];
    }
}
