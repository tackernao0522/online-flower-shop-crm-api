<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class StoreCustomerRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'name' => 'required|string|max:100',
            'email' => 'required|email|unique:customers,email|max:100',
            'phoneNumber' => 'required|regex:/^\d{2,4}-\d{1,4}-\d{4}$/|max:20', // ハイフン付きの電話番号を許可
            'address' => 'required|string|max:255',
            'birthDate' => 'required|date',
        ];
    }

    public function messages()
    {
        return [
            'phoneNumber.regex' => '電話番号は正しい形式（例: 090-1234-5678）で入力してください。',
        ];
    }
}
