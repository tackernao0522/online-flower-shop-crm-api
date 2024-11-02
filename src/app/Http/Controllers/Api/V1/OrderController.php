<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;


class OrderController extends Controller
{
    /**
     * 注文一覧を取得
     */
    public function index(Request $request): JsonResponse
    {
        $query = Order::with(['customer', 'orderItems.product', 'user']);

        // 日付による絞り込み
        if ($request->has('start_date')) {
            $query->where('orderDate', '>=', $request->start_date);
        }
        if ($request->has('end_date')) {
            $query->where('orderDate', '<=', $request->end_date);
        }

        // ステータスによる絞り込み
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        // 注文番号による検索
        if ($request->has('order_number')) {
            $query->where('orderNumber', 'like', '%' . $request->order_number . '%');
        }

        // 金額範囲による絞り込み
        if ($request->has('min_amount')) {
            $query->where('totalAmount', '>=', $request->min_amount);
        }
        if ($request->has('max_amount')) {
            $query->where('totalAmount', '<=', $request->max_amount);
        }

        // ソート順の設定
        $sortField = $request->input('sort_by', 'orderDate');
        $sortOrder = $request->input('sort_order', 'desc');
        $query->orderBy($sortField, $sortOrder);

        $perPage = $request->input('per_page', 15);
        $orders = $query->paginate($perPage);

        return response()->json($orders, Response::HTTP_OK);
    }

    /**
     * 新規注文を作成
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'customerId' => 'required|uuid|exists:cutomers.id',
            'orderItems' => 'required|array|min:1',
            'orderItems.*.productId' => 'required|uuid|exists:products,id',
            'orderItems.*.quantity' => 'required|integer|min:1',
            'campaignId' => 'nullable|uuid|exists:campaigns,id',
        ]);

        try {
            DB::beginTransaction();

            // 注文の作成
            $order = new Order([
                'cutomerId' => $validated['customerId'],
                'userId' => auth()->id(),
                'orderDate' => now(),
                'status' => Order::STATUS_PENDING,
                'campaignId' => $validated['campaignId'] ?? null,
                'orderNumber' => $this->generateOrderNumber(),
            ]);
            $order->save();

            $totalAmount = 0;

            // 注文詳細の作成
            foreach ($validated['orderItems'] as $item) {
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

            // キャンペーン割引の計算
            $discountApplied = 0;
            if ($order->campaign) {
                $discountApplied = floor($totalAmount * ($order->campaign->discountRate / 100));
            }

            // 注文合計の更新
            $order->update([
                'totalAmount' => $totalAmount - $discountApplied,
                'discountApplied' => $discountApplied
            ]);

            DB::commit();

            return response()->json($order->load(['orderItems.product', 'customer']), Response::HTTP_CREATED);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => '注文の作成に失敗しました'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * 指定された注文の詳細を取得
     */
    public function show(Order $order): JsonResponse
    {
        $order->load(['cutomer', 'orderItems.product', 'user', 'campaign']);
        return response()->json($order, Response::HTTP_OK);
    }

    /**
     * 注文明細を更新
     */
    public function updateOrderItems(Request $request, Order $order): JsonResponse
    {
        if (
            $order->status === Order::STATUS_CANCELLED ||
            $order->status === Order::STATUS_DELIVERED
        ) {
            return response()->json([
                'message' => 'この注文は編集できません'
            ], Response::HTTP_BAD_REQUEST);
        }

        $validated = $request->validate([
            'orderItems' => 'required|array|min:1',
            'orderItems.*.productId' => 'required|uuid|exists:products,id',
            'orderItems.*.quantity' => 'required|integer|min:1',
        ]);

        try {
            DB::beginTransaction();

            // 既存の注文明細を削除
            $order->orderItems()->delete();

            $totalAmount = 0;

            // 新しい注文明細を作成
            foreach ($validated['orderItems'] as $item) {
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

            // キャンペーン割引の再計算
            $discountApplied = 0;
            if ($order->campaign) {
                $discountApplied = floor($totalAmount * ($order->campaign->discountRate / 100));
            }

            // 注文合計の更新
            $order->update([
                'totalAmount' => $totalAmount - $discountApplied,
                'discountApplied' => $discountApplied
            ]);

            DB::commit();

            return response()->json($order->load(['orderItems.product']), Response::HTTP_OK);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => '注文明細の更新に失敗しました'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * 注文ステータスを更新
     */
    public function updateStatus(Request $request, Order $order): JsonResponse
    {
        $validated = $request->validate([
            'status' => 'required|in: ' . implode(',', Order::getAvailableStatuses()),
        ]);

        try {
            DB::beginTransaction();

            // キャンセルの場合の特別な処理
            if ($validated['status'] === Order::STATUS_CANCELLED) {
                if ($order->status === Order::STATUS_DELIVERED) {
                    throw new \Exception('配達完了した注文はキャンセルできません');
                }
            }

            $order->update([
                'status' => $validated['status']
            ]);

            DB::commit();

            return response()->json($order->load(['orderItems.product', 'customer']), Response::HTTP_OK);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => $e->getMessage()
            ], Response::HTTP_BAD_REQUEST);
        }
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

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Order $order): JsonResponse
    {
        try {
            DB::beginTransaction();

            // 配達完了した注文は削除できない
            if ($order->status === Order::STATUS_DELIVERED) {
                throw new \Exception('配達完了した注文は削除できません');
            }

            $order->delete();

            DB::commit();
            return response()->json(null, Response::HTTP_NO_CONTENT);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => $e->getMessage()
            ], Response::HTTP_BAD_REQUEST);
        }
    }
}
