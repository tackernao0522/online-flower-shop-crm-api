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
            'phoneNumber' => 'required|regex:/^\d+$/|max:20',
            'address' => 'required|string|max:255',
            'birthDate' => 'required|date',
        ];
    }
}
