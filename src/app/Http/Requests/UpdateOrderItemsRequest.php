<?php

namespace App\Http\Requests;

use App\Models\Order;
use Illuminate\Foundation\Http\FormRequest;

class UpdateOrderItemsRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $order = $this->route('order');
        return !in_array($order->status, [
            Order::STATUS_CANCELLED,
            Order::STATUS_DELIVERED
        ]);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'orderItems' => 'required|array|min:1',
            'orderItems.*.productId' => 'required|uuid|exists:products,id',
            'orderItems.*.quantity' => 'required|integer|min:1',
        ];
    }

    public function messages(): array
    {
        return [
            'orderItems.required' => '注文明細は必須です',
            'orderItems.min' => '最低1つの商品を指定ください',
            'orderItems.*.productId.required' => '商品IDは必須です',
            'orderItems.*.productId.exists' => '指定された商品が見つかりません',
            'orderItems.*.quantity.required' => '数量は必須です',
            'orderItems.*.quantity.min' => '数量は1以上を指定してください',
        ];
    }

    public function failedAuthorization()
    {
        throw new \Illuminate\Http\Exceptions\HttpResponseException(
            response()->json([
                'message' => 'この注文は編集できません'
            ], 403)
        );
    }
}
