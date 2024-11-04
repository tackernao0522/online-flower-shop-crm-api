<?php

namespace App\Http\Requests\Api\V1;

use App\Models\Shipment;
use Illuminate\Foundation\Http\FormRequest;

class StoreShipmentRequest extends FormRequest
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
            'orderId' => 'required|uuid|exists:orders,id',
            'shippingDate' => 'required|date',
            'status' => 'required|in:' . implode(',', Shipment::getAvailableStatuses()),
            'trackingNumber' => 'nullable|string|max:255',
            'deliveryNote' => 'nullable|string',
        ];
    }

    public function messages(): array
    {
        return [
            'orderId.required' => '注文IDは必須です',
            'orderId.exists' => '指定された注文が存在しません',
            'shippingDate.required' => '配送日は必須です',
            'status.required' => '配送状態は必須です',
            'status.in' => '無効な配送状態が指定されました',
        ];
    }
}
