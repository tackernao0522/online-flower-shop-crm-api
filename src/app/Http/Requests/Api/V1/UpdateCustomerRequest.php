<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCustomerRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'name' => 'sometimes|required|string|max:100',
            'email' => 'sometimes|required|email|unique:customers,email,' . $this->customer->id . '|max:100',
            'phoneNumber' => 'sometimes|required|regex:/^\d{2,4}-\d{1,4}-\d{4}$/|max:20',
            'address' => 'sometimes|required|string|max:255',
            'birthDate' => 'sometimes|required|date',
        ];
    }

    public function messages()
    {
        return ['phoneNumber.regex' => '電話番号は正しい形式（例: 090-1234-5678）で入力してください。',];
    }
}
