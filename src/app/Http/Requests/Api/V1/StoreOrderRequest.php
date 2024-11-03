<?php

namespace App\Http\Requests\Api\V1;

use App\Models\Order;
use Illuminate\Foundation\Http\FormRequest;

class StoreOrderRequest extends FormRequest
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
            'customerId' => 'required|uuid|exists:cutomers.id',
            'orderItems' => 'required|array|min:1',
            'orderItems.*.productId' => 'required|uuid|exists:products,id',
            'orderItems.*.quantity' => 'required|integer|min:1',
            'campaignId' => 'nullable|uuid|exists:campaigns,id',
        ];
    }

    public function messages(): array
    {
        return [
            'customerId.required' => '顧客IDは必須です',
            'customerId.exists' => '指定された顧客が見つかりません',
            'orderItems.required' => '注文明細は必須です',
            'orderItems.min' => '最低1つの商品を指定してください',
            'orderItems.*.productId.required' => '商品IDは必須です',
            'orderItems.*.productId.exists' => '指定された注文の詳細を取得商品が見つかりません',
            'orderItems.*.quantity.min' => '数量は1以上を指定してください',
            'campaignId.exists' => '指定されたキャンペーンが見つかりません',
        ];
    }
}
