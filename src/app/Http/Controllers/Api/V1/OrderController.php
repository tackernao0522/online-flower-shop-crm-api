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
        $query = Order::with(['customer', 'orderItems.product', 'user'])
            ->dateRange($request->start_date, $request->end_date)
            ->withStatus($request->status)
            ->orderNumberLike($request->order_number)
            ->amountRange($request->min_amount, $request->max_amount)
            ->orderBy(
                $request->input('sort_by', 'orderDate'),
                $request->input('sort_order', 'desc')
            );

        $orders = $query->paginate($request->input('per_page', 15));

        return response()->json($orders, Response::HTTP_OK);
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

            return response()->json(
                $order->load(['orderItems.product', 'customer']),
                Response::HTTP_CREATED
            );
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(
                ['message' => '注文の作成に失敗しました'],
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }
    }

    /**
     * 指定された注文の詳細を取得
     */
    public function show(Order $order): JsonResponse
    {
        return response()->json(
            $order->load(['customer', 'orderItems.product', 'user', 'campaign']),
            Response::HTTP_OK
        );
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

            return response()->json(
                $order->load(['orderItems.product']),
                Response::HTTP_OK
            );
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(
                ['message' => '注文詳細の更新に失敗しました'],
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
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
            return response()->json(
                ['message' => $e->getMessage()],
                Response::HTTP_BAD_REQUEST
            );
        }
    }

    /**
     * Remove the specified resource from storage.
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
            return response()->json(
                ['message' => $e->getMessage()],
                Response::HTTP_BAD_REQUEST
            );
        }
    }
}
