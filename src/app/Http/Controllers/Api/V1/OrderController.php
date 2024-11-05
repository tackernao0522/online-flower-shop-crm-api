<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreOrderRequest;
use App\Http\Requests\Api\V1\UpdateOrderItemsRequest;
use App\Http\Requests\Api\V1\UpdateOrderStatusRequest;
use App\Models\Order;
use App\Services\OrderService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
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

            // データを変換して、明示的にリレーションを含める
            $orders->getCollection()->transform(function ($order) {
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

            return response()->json($orders, Response::HTTP_OK);
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

            $order = $this->orderService->createOrder($request->validated());

            DB::commit();

            // レスポンスを明示的に構造化
            return response()->json([
                'id' => $order->id,
                'orderNumber' => $order->orderNumber,
                'totalAmount' => $order->totalAmount,
                'status' => $order->status,
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
    public function updateStatus(UpdateOrderStatusRequest $request, Order $order): JsonResponse
    {
        try {
            DB::beginTransaction();

            $order->update(['status' => $request->status]);

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