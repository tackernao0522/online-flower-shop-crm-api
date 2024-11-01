<?php

namespace Database\Factories;

use App\Models\Campaign;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Campaign>
 */
class CampaignFactory extends Factory
{
    protected $model = Campaign::class;

    /**
     * キャンペーンテンプレートの定義
     */
    private function getCampaignTemplates(): array
    {
        return [
            [
                'name' => '母の日特別キャンペーン',
                'description' => '感謝の気持ちを込めて、母の日限定の特別な花束やアレンジメントをお届けします。期間限定の特別価格でご提供。',
            ],
            [
                'name' => 'サマーフラワーフェスティバル',
                'description' => '夏の花々を使用した季節限定アレンジメント。暑い季節を涼やかに彩る特別コレクション。',
            ],
            [
                'name' => '開店・開業祝い応援フェア',
                'description' => '胡蝶蘭やアレンジメントを特別価格で。新しい門出を華やかに演出するセレクション。',
            ],
            [
                'name' => 'クリスマスシーズン特別企画',
                'description' => '華やかなクリスマスアレンジメントを期間限定価格で。大切な方への贈り物に最適なセレクション。',
            ],
            [
                'name' => '春の卒業・入学祝いキャンペーン',
                'description' => '門出を祝う特別なフラワーギフト。心に残る思い出作りのお手伝い。',
            ],
            [
                'name' => 'バレンタイン＆ホワイトデーフェア',
                'description' => '愛を伝える特別なフラワーアレンジメント。大切な方への気持ちを花に込めて。',
            ],
            [
                'name' => '記念日応援キャンペーン',
                'description' => '結婚記念日や誕生日などの大切な日を、特別な花々で演出。記念日に相応しい上質なアレンジメント。',
            ],
            [
                'name' => '秋の感謝祭セール',
                'description' => '秋の花々を使用した季節限定デザイン。実りの季節を彩る特別コレクション。',
            ],
            [
                'name' => '新生活応援フェア',
                'description' => '新生活のスタートを華やかに。お部屋を彩る観葉植物や花々を特別価格で。',
            ],
            [
                'name' => 'ウェディングフラワーフェア',
                'description' => 'ブライダルシーズンに向けた特別企画。ウェディングブーケやブライダル装飾の特別パッケージ。',
            ],
        ];
    }

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $template = $this->faker->randomElement($this->getCampaignTemplates());
        $startDate = $this->faker->dateTimeBetween('now', '+2 months');
        $endDate = (clone $startDate)->modify('+2 weeks');

        return [
            'name' => $template['name'],
            'description' => $template['description'],
            'startDate' => $startDate,
            'endDate' => $endDate,
            'discountRate' => $this->faker->numberBetween(5, 30), // 5%〜30%の割引
            'discountCode' => strtoupper($this->faker->bothify('FLOWER##??')), // 例: FLOWER25AB
            'is_active' => $this->faker->boolean(80), // 80%の確率でアクティブ

        ];
    }

    /**
     * 進行中のキャンペーン設定
     */
    public function active()
    {
        return $this->state(function (array $attributes) {
            $startDate = now()->subDays(3);
            return [
                'startDate' => $startDate,
                'endDate' => $startDate->copy()->addDays(14),
                'is_active' => true,
            ];
        });
    }

    /**
     * 終了したキャンペーンを設定
     */
    public function past()
    {
        return $this->state(function (array $attributes) {
            $startDate = now()->subMonths(2);
            return [
                'startDate' => $startDate,
                'endDate' => $startDate->copy()->addDays(14),
                'is_active' => false,
            ];
        });
    }
}
