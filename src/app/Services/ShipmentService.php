<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Shipment;

class ShipmentService
{
    /**
     * 配送情報を作成
     */
    public function createShipment(array $data): Shipment
    {
        // 注文が既に配送情報を持っているかチェック
        if (Shipment::where('orderId', $data['orderId'])->exists()) {
            throw new \Exception('この注文の配送情報は既に存在します');
        }

        $shipment = Shipment::create($data);

        // 注文のステータスを更新
        $this->updateOrderStatus($shipment);

        return $shipment;
    }

    /**
     * 配送情報を更新
     */
    public function UpdateShipment(Shipment $shipment, array $data): Shipment
    {
        $shipment->update($data);

        // 注文のステータスを更新
        $this->updateOrderStatus($shipment);

        return $shipment;
    }

    /**
     * 配送情報を削除
     */
    public function deleteShipment(Shipment $shipment): void
    {
        if (in_array($shipment->status, [
            Shipment::STATUS_SHIPPED,
            Shipment::STATUS_IN_TRANSIT,
            Shipment::STATUS_DELIVERED
        ])) {
            throw new \Exception('配送処理が開始された配送情報は削除できません');
        }

        $shipment->delete();
    }

    /**
     * 注文のステータスを更新
     */
    private function updateOrderStatus(Shipment $shipment): void
    {
        $order = $shipment->order;

        switch ($shipment->status) {
            case Shipment::STATUS_SHIPPED:
                $order->update(['status' => Order::STATUS_SHIPPED]);
                break;
            case Shipment::STATUS_DELIVERED:
                $order->update(['status' => Order::STATUS_DELIVERED]);
                break;
        }
    }
}
