<?php

namespace App\Http\Requests\Api\V1;

use App\Models\Shipment;
use Illuminate\Foundation\Http\FormRequest;

class UpdateShipmentRequest extends FormRequest
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
            'shippingDate' => 'sometimes|required|date',
            'status' => 'sometimes|required|in:' . implode(',', Shipment::getAvailableStatuses()),
            'trackingNumber' => 'nullable|string|max:255',
            'deliveryNote' => 'nullable|string',
        ];
    }

    public function messages(): array
    {
        return [
            'shippingDate.required' => '配送日は必須です',
            'status.required' => '配送状態は必須です',
            'status.in' => '無効な配送状態が指定されました',
        ];
    }

    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            $shipment = $this->route('shipment');
            if (
                $shipment->status === Shipment::STATUS_DELIVERED &&
                $this->has('status') &&
                $this->status !== Shipment::STATUS_DELIVERED
            ) {
                $validator->errors()->add('status', '配達完了した配送の状態は変更できません');
            }
        });
    }
}
