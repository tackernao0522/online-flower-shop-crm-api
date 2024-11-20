<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreOrderRequest;
use App\Http\Requests\Api\V1\UpdateOrderItemsRequest;
use App\Http\Requests\Api\V1\UpdateOrderStatusRequest;
use App\Models\Order;
use App\Services\OrderService;
use App\Services\StatsService;
use App\Events\OrderCountUpdated;
use App\Events\SalesUpdated;
use App\Models\StatsLog;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class OrderController extends Controller
{
    private $orderService;
    private $statsService;

    public function __construct(
        OrderService $orderService,
        StatsService $statsService
    ) {
        $this->orderService = $orderService;
        $this->statsService = $statsService;
    }


    /**
     * 注文一覧を取得
     */
    public function index(Request $request): JsonResponse
    {
        try {
            Log::info('Building Order query', ['request' => $request->all()]);

            $query = Order::with(['customer', 'orderItems.product', 'user']);

            if ($request->has('search')) {
                $query->customerNameLike($request->input('search'));
            }

            // フィルタリング処理
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

            // アクティブな注文のみを取得して統計を計算
            $activeOrders = Order::whereNotIn('status', [Order::STATUS_CANCELLED])
                ->whereNull('deleted_at')
                ->with('orderItems')
                ->get();

            // 現在の統計を計算
            $currentCount = $activeOrders->sum(function ($order) {
                return $order->orderItems->sum('quantity');
            });
            $currentSales = $activeOrders->sum('totalAmount');

            // 最新の統計ログを取得
            $orderStats = StatsLog::where('metric_type', 'order_count')
                ->latest('recorded_at')
                ->first();
            $salesStats = StatsLog::where('metric_type', 'sales')
                ->latest('recorded_at')
                ->first();

            // StatsLogモデルから返される値を調整
            $formattedOrderStats = [
                'currentValue' => $currentCount,
                'previousValue' => $orderStats ? $orderStats->previous_value : $currentCount,
                'changeRate' => $orderStats ? $orderStats->change_rate : 0
            ];

            $formattedSalesStats = [
                'currentValue' => $currentSales,
                'previousValue' => $salesStats ? $salesStats->previous_value : $currentSales,
                'changeRate' => $salesStats ? $salesStats->change_rate : 0
            ];

            // レスポンスデータを構築
            $responseData = [
                'data' => $orders,
                'meta' => [
                    'current_page' => $orders->currentPage(),
                    'total_pages' => $orders->lastPage(),
                    'total' => $orders->total(),
                ],
                'stats' => [
                    'totalCount' => $formattedOrderStats['currentValue'],
                    'previousCount' => $formattedOrderStats['previousValue'],
                    'changeRate' => $formattedOrderStats['changeRate'],
                    'totalSales' => $formattedSalesStats['currentValue'],
                    'previousSales' => $formattedSalesStats['previousValue'],
                    'salesChangeRate' => $formattedSalesStats['changeRate']
                ]
            ];

            Log::info('Stats Debug', [
                'orderStats' => $orderStats,
                'orderStats_type' => gettype($orderStats),
                'orderStats_class' => $orderStats ? get_class($orderStats) : null,
                'salesStats' => $salesStats,
                'salesStats_type' => gettype($salesStats),
                'salesStats_class' => $salesStats ? get_class($salesStats) : null
            ]);

            return response()->json($responseData, Response::HTTP_OK);
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

            // 注文を作成
            $validatedData = $request->validated();
            $validatedData['orderDate'] = now();
            $order = $this->orderService->createOrder($validatedData);

            // アクティブな注文を取得して統計計算
            $activeOrders = Order::whereNotIn('status', [Order::STATUS_CANCELLED])
                ->whereNull('deleted_at')
                ->with('orderItems')
                ->get();

            $currentOrderCount = $activeOrders->sum(function ($order) {
                return $order->orderItems->sum('quantity');
            });
            $currentSales = $activeOrders->sum('totalAmount');

            // 統計更新を一度だけ行い、結果を保持
            $orderStats = $this->statsService->updateStats('order_count', $currentOrderCount);
            $salesStats = $this->statsService->updateStats('sales', $currentSales);

            // WebSocket通知
            broadcast(new OrderCountUpdated(
                $orderStats['currentValue'],
                $orderStats['previousValue'],
                $orderStats['changeRate']
            ));

            broadcast(new SalesUpdated(
                $salesStats['currentValue'],
                $salesStats['previousValue'],
                $salesStats['changeRate']
            ));

            Log::info('Order created and stats updated', [
                'order_id' => $order->id,
                'stats' => [
                    'orders' => [
                        'current' => $currentOrderCount,
                        'previous' => $orderStats['previousValue'],
                        'change' => $orderStats['changeRate']
                    ],
                    'sales' => [
                        'current' => $currentSales,
                        'previous' => $salesStats['previousValue'],
                        'change' => $salesStats['changeRate']
                    ]
                ]
            ]);

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
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                'error' => [
                    'code' => 'ORDER_CREATE_ERROR',
                    'message' => '注文の作成に失敗しました'
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
            $orderWithRelations = $order->load(['customer', 'orderItems.product', 'user', 'campaign']);

            // ログにデータを記録する
            Log::info('Order Details with Relations:', $orderWithRelations->toArray());

            return response()->json($orderWithRelations, Response::HTTP_OK);
        } catch (\Exception $e) {
            Log::error('注文情報の取得に失敗しました:', ['error' => $e->getMessage()]);

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

            // 注文明細を更新
            $order = $this->orderService->updateOrderItems($order, $request->orderItems);

            // アクティブな注文を取得して統計計算（ステータスがキャンセルでない注文のみ）
            $activeOrders = Order::whereNotIn('status', [Order::STATUS_CANCELLED])
                ->whereNull('deleted_at')
                ->with('orderItems')
                ->get();

            // 注文数と売上の現在値を計算
            $currentOrderCount = $activeOrders->sum(function ($order) {
                return $order->orderItems->sum('quantity');
            });
            $currentSales = $activeOrders->sum('totalAmount');

            // 最新の統計を取得
            $latestOrderStats = StatsLog::where('metric_type', 'order_count')
                ->latest('recorded_at')
                ->first();
            $latestSalesStats = StatsLog::where('metric_type', 'sales')
                ->latest('recorded_at')
                ->first();

            // 値が実際に変化している場合のみ更新
            if ($latestOrderStats->current_value !== $currentOrderCount) {
                $orderStats = $this->statsService->updateStats('order_count', $currentOrderCount);

                broadcast(new OrderCountUpdated(
                    $orderStats['currentValue'],
                    $orderStats['previousValue'],
                    $orderStats['changeRate']
                ));
            }

            if ($latestSalesStats->current_value !== $currentSales) {
                $salesStats = $this->statsService->updateStats('sales', $currentSales);

                broadcast(new SalesUpdated(
                    $salesStats['currentValue'],
                    $salesStats['previousValue'],
                    $salesStats['changeRate']
                ));
            }

            Log::info('Order items updated and stats recalculated', [
                'order_id' => $order->id,
                'actual_stats' => [
                    'order_count' => $currentOrderCount,
                    'total_sales' => $currentSales
                ]
            ]);

            DB::commit();

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
            Log::error('Order items update failed', [
                'order_id' => $order->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
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
    public function updateStatus(UpdateOrderStatusRequest $request, Order $order): JsonResponse
    {
        try {
            DB::beginTransaction();

            $originalStatus = $order->status;
            $newStatus = $request->status;

            // ステータス更新
            $order->update(['status' => $newStatus]);

            // キャンセル時のみ統計を更新
            if ($newStatus === Order::STATUS_CANCELLED) {
                // アクティブな注文を取得して統計計算
                $activeOrders = Order::whereNotIn('status', [Order::STATUS_CANCELLED])
                    ->whereNull('deleted_at')
                    ->with('orderItems')
                    ->get();

                $currentOrderCount = $activeOrders->sum(function ($order) {
                    return $order->orderItems->sum('quantity');
                });
                $currentSales = $activeOrders->sum('totalAmount');

                // 統計更新を1回のトランザクションで実行
                $stats = DB::transaction(function () use ($currentOrderCount, $currentSales) {
                    $orderStats = $this->statsService->updateStats('order_count', $currentOrderCount);
                    $salesStats = $this->statsService->updateStats('sales', $currentSales);

                    return [
                        'orders' => $orderStats,
                        'sales' => $salesStats
                    ];
                });

                // イベント発行
                broadcast(new OrderCountUpdated(
                    $currentOrderCount,
                    $stats['orders']['previousValue'],
                    $stats['orders']['changeRate']
                ));

                broadcast(new SalesUpdated(
                    $currentSales,
                    $stats['sales']['previousValue'],
                    $stats['sales']['changeRate']
                ));

                Log::info('Order cancelled and stats updated', [
                    'order_id' => $order->id,
                    'previous_status' => $originalStatus,
                    'new_status' => $newStatus,
                    'final_stats' => [
                        'active_orders' => $currentOrderCount,
                        'total_sales' => $currentSales,
                        'order_change' => $stats['orders']['changeRate'],
                        'sales_change' => $stats['sales']['changeRate']
                    ]
                ]);
            }

            DB::commit();

            return response()->json(
                $order->load(['orderItems.product', 'customer']),
                Response::HTTP_OK
            );
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Status update failed', [
                'order_id' => $order->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                'error' => 'ステータスの更新に失敗しました。'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * 指定された注文を削除
     */
    public function destroy(Order $order): JsonResponse
    {
        try {
            DB::beginTransaction();

            if (!$order->canBeCancelled()) {
                return response()->json([
                    'error' => [
                        'code' => 'ORDER_CANNOT_BE_DELETED',
                        'message' => 'この注文は削除できません'
                    ]
                ], Response::HTTP_BAD_REQUEST);
            }

            // 削除前の統計を取得
            $activeOrders = Order::whereNotIn('status', [Order::STATUS_CANCELLED])
                ->whereNull('deleted_at')
                ->with('orderItems')
                ->get();

            $previousOrderCount = $activeOrders->sum(function ($order) {
                return $order->orderItems->sum('quantity');
            });
            $previousSales = $activeOrders->sum('totalAmount');

            // 注文を論理削除
            $order->delete();

            // 削除後の統計を計算
            $activeOrders = Order::whereNotIn('status', [Order::STATUS_CANCELLED])
                ->whereNull('deleted_at')
                ->with('orderItems')
                ->get();

            $currentOrderCount = $activeOrders->sum(function ($order) {
                return $order->orderItems->sum('quantity');
            });
            $currentSales = $activeOrders->sum('totalAmount');

            // 統計を更新
            $orderStats = $this->statsService->updateStats('order_count', $currentOrderCount);
            $salesStats = $this->statsService->updateStats('sales', $currentSales);

            // WebSocket通知
            broadcast(new OrderCountUpdated(
                $orderStats['currentValue'],
                $orderStats['previousValue'],
                $orderStats['changeRate']
            ));

            broadcast(new SalesUpdated(
                $salesStats['currentValue'],
                $salesStats['previousValue'],
                $salesStats['changeRate']
            ));

            Log::info('Order deleted and stats updated', [
                'order_id' => $order->id,
                'stats' => [
                    'orders' => [
                        'previous' => $previousOrderCount,
                        'current' => $currentOrderCount,
                        'change' => $orderStats['changeRate']
                    ],
                    'sales' => [
                        'previous' => $previousSales,
                        'current' => $currentSales,
                        'change' => $salesStats['changeRate']
                    ]
                ]
            ]);

            DB::commit();
            return response()->json(null, Response::HTTP_NO_CONTENT);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('注文の削除に失敗しました:', [
                'order_id' => $order->id,
                'error' => $e->getMessage()
            ]);
            return response()->json([
                'error' => [
                    'code' => 'ORDER_DELETE_ERROR',
                    'message' => '注文の削除に失敗しました'
                ]
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
