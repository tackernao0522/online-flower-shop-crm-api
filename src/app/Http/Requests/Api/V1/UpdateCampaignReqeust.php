<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCampaignReqeust extends FormRequest
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
            'name' => 'sometimes|required|string|max:100',
            'startDate' => 'sometimes|required|date',
            'endDate' => 'sometimes|required|date|after:startDate',
            'discountRate' => 'sometimes|required|integer|min:1|max:100',
            'discountCode' => 'sometimes|required|string|max:50|unique:campaigns,discountCode,' . $this->campaign->id,
            'description' => 'nullable|string',
            'is_active' => 'sometimes|required|boolean'
        ];
    }

    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            $campaign = $this->route('campaign');
            if (
                $campaign->orders()->exists() &&
                $this->has('discountRate') &&
                $campaign->discountRate != $this->discountRate
            ) {
                $validator->errors()->add('discountRate', '使用済みのキャンペーンの割引率は変更できません');
            }
        });
    }

    public function messages(): array
    {
        return [
            'name.required' => 'キャンペーン名は必須です',
            'startDate.required' => '開始日は必須です',
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
