<?php

namespace App\Http\Requests\Api\V1;

use App\Models\Order;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateOrderStatusRequest extends FormRequest
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
            'status' => ['required', Rule::in(Order::getAvailableStatuses())],
        ];
    }

    public function messages(): array
    {
        return [
            'status.required' => 'ステータスは必須です',
            'status.in' => '無効なステータスが指定されました',
        ];
    }

    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            if ($validator->errors()->isEmpty()) {
                $order = $this->route('order');
                if (
                    $this->status === Order::STATUS_CANCELLED &&
                    $order->status === Order::STATUS_DELIVERED
                ) {
                    $validator->errors()->add('status', '配達完了した注文はキャンセルできません');
                }
            }
        });
    }

    protected function failedValidation(\Illuminate\Contracts\Validation\Validator $validator)
    {
        throw new \Illuminate\Validation\ValidationException($validator, response()->json([
            'errors' => $validator->errors()
        ], 422));
    }
}
