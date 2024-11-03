<?php

namespace App\Services;

use App\Models\Campaign;

class CampaignService
{
    /**
     * キャンペーン詳細情報を取得
     */
    public function getCampaignDetails(Campaign $campaign): array
    {
        $campaign->load('orders');
        $isCurrentlyActive = $campaign->isValid();

        return array_merge($campaign->toArray(), [
            'isCurrentlyActive' => $isCurrentlyActive,
            'ordersCount' => $campaign->orders->count(),
            'totalDiscountAmount' => $campaign->orders->sum('discountApplied')
        ]);
    }

    /**
     * キャンペーンを作成
     */
    public function createCampaign(array $data): Campaign
    {
        return Campaign::create($data);
    }

    /**
     * キャンペーンを更新
     */
    public function updateCampaign(Campaign $campaign, array $data): Campaign
    {
        $campaign->update($data);
        return $campaign;
    }

    /**
     * キャンペーンを削除
     */
    public function deleteCampaign(Campaign $campaign): void
    {
        if ($campaign->orders()->exists()) {
            throw new \Exception('使用中のキャンペーンは削除できません');
        }

        $campaign->delete();
    }

    /**
     * キャンペーンのステータスを切り替え
     */
    public function toggleStatus(Campaign $campaign): Campaign
    {
        $campaign->update([
            'is_active' => !$campaign->is_active
        ]);

        return $campaign;
    }
}
