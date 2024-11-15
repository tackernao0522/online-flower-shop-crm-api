<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreOrderRequest;
use App\Http\Requests\Api\V1\UpdateOrderItemsRequest;
use App\Http\Requests\Api\V1\UpdateOrderStatusRequest;
use App\Models\Order;
use App\Services\OrderService;
use App\Events\OrderCountUpdated;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class OrderController extends Controller
{
    private $orderService;

    public function __construct(OrderService $orderService)
    {
        $this->orderService = $orderService;
    }

    /**
     * 注文一覧を取得
     */
    public function index(Request $request): JsonResponse
    {
        try {
            Log::info('Building Order query', ['request' => $request->all()]);

            $query = Order::with(['customer', 'orderItems.product', 'user']);

            // 現在の注文数を取得してstatsを更新（キャンセル以外）
            $currentCount = Order::whereNotIn('status', ['CANCELLED'])->count();
            $currentStats = $this->updateOrderStats($currentCount);

            if ($request->has('start_date') || $request->has('end_date')) {
                $query->dateRange($request->start_date, $request->end_date);
            }

            if ($request->has('status')) {
                $query->withStatus($request->status);
            }

            if ($request->has('min_amount') || $request->has('max_amount')) {
                $query->amountRange($request->min_amount, $request->max_amount);
            }

            $orders = $query->orderBy(
                $request->input('sort_by', 'orderDate'),
                $request->input('sort_order', 'desc')
            )->paginate($request->input('per_page', 15));

            $transformedOrders = $orders->getCollection()->map(function ($order) {
                return [
                    'id' => $order->id,
                    'orderNumber' => $order->orderNumber,
                    'orderDate' => $order->orderDate,
                    'totalAmount' => $order->totalAmount,
                    'status' => $order->status,
                    'customer' => $order->customer,
                    'orderItems' => $order->orderItems->map(function ($item) {
                        return [
                            'id' => $item->id,
                            'quantity' => $item->quantity,
                            'unitPrice' => $item->unitPrice,
                            'product' => $item->product
                        ];
                    })
                ];
            });

            $response = [
                'data' => $transformedOrders,
                'meta' => [
                    'current_page' => $orders->currentPage(),
                    'per_page' => $orders->perPage(),
                    'total' => $orders->total(),
                    'last_page' => $orders->lastPage(),
                ],
                'stats' => [
                    'totalCount' => $currentStats['totalCount'],
                    'previousCount' => $currentStats['previousCount'],
                    'changeRate' => $currentStats['changeRate']
                ]
            ];

            Log::info('Order stats calculated', $currentStats);
            Log::info('Order index response prepared', [
                'totalCount' => $currentStats['totalCount'],
                'changeRate' => $currentStats['changeRate']
            ]);

            return response()->json($response, Response::HTTP_OK);
        } catch (\Exception $e) {
            Log::error('Order index error:', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'error' => [
                    'code' => 'ORDER_ERROR',
                    'message' => '注文情報の取得に失敗しました。'
                ]
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * 新規注文を作成
     */
    public function store(StoreOrderRequest $request): JsonResponse
    {
        try {
            DB::beginTransaction();

            // 作成前の合計を取得
            $previousTotalCount = Order::whereNotIn('status', ['CANCELLED'])->count();

            // 注文を作成し、orderDate に現在日時を設定
            $validatedData = $request->validated();
            $validatedData['orderDate'] = now(); // orderDate を現在の日時に設定
            $order = $this->orderService->createOrder($validatedData);

            // 作成後の合計を取得
            $currentTotalCount = Order::whereNotIn('status', ['CANCELLED'])->count();

            // 変化率を計算
            $changeRate = $previousTotalCount > 0
                ? round((($currentTotalCount - $previousTotalCount) / $previousTotalCount) * 100, 1)
                : 0;

            // イベントを発行
            broadcast(new OrderCountUpdated(
                $currentTotalCount,
                $previousTotalCount,
                $changeRate
            ));

            DB::commit();

            return response()->json([
                'id' => $order->id,
                'orderNumber' => $order->orderNumber,
                'totalAmount' => $order->totalAmount,
                'status' => $order->status,
                'orderDate' => $order->orderDate,
                'orderItems' => $order->orderItems->map(function ($item) {
                    return [
                        'quantity' => $item->quantity,
                        'unitPrice' => $item->unitPrice,
                        'product' => $item->product
                    ];
                }),
                'customer' => $order->customer
            ], Response::HTTP_CREATED);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Order creation failed', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                'error' => [
                    'code' => 'ORDER_ERROR',
                    'message' => '注文の作成に失敗しました。'
                ]
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * 指定された注文の詳細を取得
     */
    public function show(Order $order): JsonResponse
    {
        try {
            return response()->json(
                $order->load(['customer', 'orderItems.product', 'user', 'campaign']),
                Response::HTTP_OK
            );
        } catch (\Exception $e) {
            return response()->json([
                'error' => [
                    'code' => 'ORDER_ERROR',
                    'message' => '注文情報の取得に失敗しました。'
                ]
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * 注文明細を更新
     */
    public function updateOrderItems(UpdateOrderItemsRequest $request, Order $order): JsonResponse
    {
        try {
            DB::beginTransaction();

            $order = $this->orderService->updateOrderItems($order, $request->orderItems);

            DB::commit();

            // レスポンスを明示的に構造化
            return response()->json([
                'id' => $order->id,
                'orderNumber' => $order->orderNumber,
                'totalAmount' => $order->totalAmount,
                'orderItems' => $order->orderItems->map(function ($item) {
                    return [
                        'quantity' => $item->quantity,
                        'unitPrice' => $item->unitPrice,
                        'product' => $item->product
                    ];
                })
            ], Response::HTTP_OK);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'error' => [
                    'code' => 'ORDER_ERROR',
                    'message' => '注文明細の更新に失敗しました。'
                ]
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * 注文ステータスを更新
     */
    /**
     * 注文ステータスを更新
     */
    public function updateStatus(UpdateOrderStatusRequest $request, Order $order): JsonResponse
    {
        try {
            DB::beginTransaction();

            // 変更前のステータスを保持
            $oldStatus = $order->status;

            // ステータスを更新
            $order->update(['status' => $request->status]);

            // キャンセルされた場合またはキャンセルから他のステータスに変更された場合は統計を更新
            if (($request->status === Order::STATUS_CANCELLED && $oldStatus !== Order::STATUS_CANCELLED) ||
                ($oldStatus === Order::STATUS_CANCELLED && $request->status !== Order::STATUS_CANCELLED)
            ) {

                // 現在の注文数を取得して統計を更新（キャンセル以外）
                $currentCount = Order::whereNotIn('status', ['CANCELLED'])->count();
                $stats = $this->updateOrderStats($currentCount);

                // イベントを発行
                broadcast(new OrderCountUpdated(
                    $stats['totalCount'],
                    $stats['previousCount'],
                    $stats['changeRate']
                ));
            }

            DB::commit();

            return response()->json(
                $order->load(['orderItems.product', 'customer']),
                Response::HTTP_OK
            );
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'error' => [
                    'code' => 'ORDER_ERROR',
                    'message' => 'ステータスの更新に失敗しました。'
                ]
            ], Response::HTTP_BAD_REQUEST);
        }
    }

    /**
     * 指定された注文を削除
     */
    public function destroy(Order $order): JsonResponse
    {
        try {
            DB::beginTransaction();

            if ($order->status === Order::STATUS_DELIVERED) {
                throw new \Exception('配達完了した注文は削除できません');
            }

            $order->delete();

            // 現在の注文数を取得して統計を更新
            $currentCount = Order::whereNotIn('status', ['CANCELLED'])->count();
            $stats = $this->updateOrderStats($currentCount);

            // イベントを発行
            broadcast(new OrderCountUpdated(
                $stats['totalCount'],
                $stats['previousCount'],
                $stats['changeRate']
            ));

            DB::commit();
            return response()->json(null, Response::HTTP_NO_CONTENT);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => $e->getMessage()
            ], Response::HTTP_BAD_REQUEST);
        }
    }

    private function updateOrderStats(int $currentCount): array
    {
        // 前回の総数を取得。ない場合は現在の数を使用
        $previousTotalCount = Cache::get('previous_order_count', $currentCount);
        $changeRate = Cache::get('order_change_rate', 0.0);

        // 現在の値が前回の値と異なる場合のみ更新
        if ($currentCount !== $previousTotalCount) {
            $changeRate = $this->calculateChangeRate($currentCount, $previousTotalCount);

            // 現在の値を次回の比較のために保存
            Cache::put('previous_order_count', $currentCount, now()->addDay());
            Cache::put('order_change_rate', $changeRate, now()->addDay());
        }

        return [
            'totalCount' => $currentCount,
            'previousCount' => $previousTotalCount,
            'changeRate' => $changeRate
        ];
    }

    /**
     * 変動率を計算
     */
    private function calculateChangeRate($currentCount, $previousCount): float
    {
        if ($previousCount == 0) {
            return $currentCount > 0 ? 100 : 0;
        }
        return round((($currentCount - $previousCount) / $previousCount) * 100, 1);
    }
}
