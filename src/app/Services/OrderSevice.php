<?php

namespace App\Services;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use Illuminate\Support\Str;

class OrderService
{
    /**
     * 新規注文を作成
     */
    public function createOrder(array $validated): Order
    {
        $order = new Order([
            'customerId' => $validated['customerId'],
            'userId' => auth()->id(),
            'orderDate' => now(),
            'status' => Order::STATUS_PENDING,
            'campaignId' => $validated['campaignId'] ?? null,
            'orderNumber' => $this->generateOrderNumber(),
        ]);
        $order->save();

        $totalAmount = $this->createOrderItems($order, $validated['orderItems']);
        $discountApplied = $this->calculateDiscount($order, $totalAmount);

        $order->update([
            'totalAmount' => $totalAmount - $discountApplied,
            'discountApplied' => $discountApplied
        ]);

        return $order;
    }

    /**
     * 注文明細を更新
     */
    public function updateOrderItems(Order $order, array $orderItems): Order
    {
        $order->orderItems()->delete();

        $totalAmount = $this->createOrderItems($order, $orderItems);
        $discountApplied = $this->calculateDiscount($order, $totalAmount);

        $order->update([
            'totalAmount' => $totalAmount - $discountApplied,
            'discountApplied' => $discountApplied
        ]);

        return $order;
    }

    /**
     * 注文明細を作成
     */
    private function createOrderItems(Order $order, array $items): int
    {
        $totalAmount = 0;

        foreach ($items as $item) {
            $product = Product::findOrFail($item['productId']);
            $orderItem = new OrderItem([
                'orderId' => $order->id,
                'productId' => $product->id,
                'quantity' => $item['quantity'],
                'unitPrice' => $product->price,
            ]);
            $orderItem->save();

            $totalAmount += $orderItem->quantity * $orderItem->unitPrice;
        }

        return $totalAmount;
    }

    /**
     * 割引額を計算
     */
    private function calculateDiscount(Order $order, int $totalAmount): int
    {
        if (!$order->campaign) {
            return 0;
        }

        return floor($totalAmount * ($order->campaign->discountRate / 100));
    }

    /**
     * 注文番号を生成
     */
    private function generateOrderNumber(): string
    {
        $date = now()->format('Ymd');
        $randomStr = strtoupper(substr(uniqid(), -4));
        return "ORD-{$date}-{$randomStr}";
    }
}
