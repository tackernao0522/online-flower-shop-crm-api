<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class StoreCampaignRequest extends FormRequest
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
            'name' => 'required|string|max:100',
            'startDate' => 'required|date|after_or_equal:today',
            'endDate' => 'required|date|after:startDate',
            'discountRate' => 'required|integer|min:1|max:100',
            'discountCode' => 'required|string|max:50|unique:campaigns',
            'description' => 'nullable|string',
            'is_active' => 'boolean'
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'キャンペーン名は必須です',
            'startDate.required' => '開始日は必須です',
            'startDate.after_or_equal' => '開始日は今日以降の日付を指定してください',
            'endDate.required' => '終了日は必須です',
            'endDate.after' => '終了日は開始日より後の日付を指定してください',
            'discountRate.required' => '割引率は必須です',
            'discountRate.min' => '割引率は1%以上で指定してください',
            'discountRate.max' => '割引率は100%以下で指定してください',
            'discountCode.required' => '割引コードは必須です',
            'discountCode.unique' => 'この割引コードは既に使用されています',
        ];
    }
}
