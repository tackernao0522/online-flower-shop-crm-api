<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Shipment;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class ShipmentController extends Controller
{
    /**
     * 配送一覧を取得
     */
    public function index(Request $request): JsonResponse
    {
        $query = Shipment::with(['order.customer']);

        // 配送日による絞り込み
        if ($request->has('start_date')) {
            $query->where('shippingDate', '>=', $request->start_date);
        }
        if ($request->has('end_date')) {
            $query->where('shippingDate', '<=', $request->end_date);
        }

        // 配送状態による絞り込み
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        // 追跡番号による検索
        if ($request->has('trackingNumber')) {
            $query->where('trackingNumber', 'like', '%' . $request->tracking_number . '%');
        }

        // 注文番号による検索
        if ($request->has('order_number')) {
            $query->whereHas('order', function ($q) use ($request) {
                $q->where('name', 'like', '%' . $request->customer_name . '%');
            });
        }

        // ソート順の設定
        $sortField = $request->input('sort_by', 'shippingDate');
        $sortOrder = $request->input('sort_order', 'desc');
        $query->orderBy($sortField, $sortOrder);

        $perPage = $request->input('per_page', 15);
        $shipments = $query->paginate($perPage);

        return response()->json($shipments, Response::HTTP_OK);
    }

    /**
     * 新規配送情報を作成
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'orderId' => 'required|uuid|exists:orders,id',
            'shippingDate' => 'required|date',
            'status' => 'required|in:' . implode(',', Shipment::getAvailableStatuses()),
            'trackingNumber' => 'nullable|string|max:255',
            'deliveryNote' => 'nullable|string',
        ]);

        try {
            DB::beginTransaction();

            // 注文が既に配送情報を持っているかチェック
            if (Shipment::where('orderId', $validated['orderId'])->exists()) {
                throw new \Exception('この注文の配送情報は既に存在します');
            }

            $shipment = Shipment::create($validated);

            // 注文のステータスを更新
            $order = Order::findOrFail($validated['orderId']);
            if ($validated['status'] === Shipment::STATUS_SHIPPED) {
                $order->update(['status' => Order::STATUS_SHIPPED]);
            }

            DB::commit();

            return response()->json(
                $shipment->load(['order.customer']),
                Response::HTTP_CREATED
            );
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        }
    }

    /**
     * 指定された配送情報の詳細を取得
     */
    public function show(Shipment $shipment): JsonResponse
    {
        $shipment->load(['order.customer', 'order.orderItems.product']);
        return response()->json($shipment, Response::HTTP_OK);
    }

    /**
     * 指定された配送情報を更新
     */
    public function update(Request $request, Shipment $shipment): JsonResponse
    {
        $validated = $request->validate([
            'shippingDate' => 'sometimes|required|date',
            'status' => 'sometimes|required|in:' . implode(',', Shipment::getAvailableStatuses()),
            'trackingNumber' => 'nullable|string|max:255',
            'deliveryNote' => 'nullable|string',
        ]);

        try {
            DB::beginTransaction();

            // 配達完了した配送の状態は変更できない
            if (
                $shipment->status === Shipment::STATUS_DELIVERED &&
                isset($validated['status']) &&
                $validated['status'] !== Shipment::STATUS_DELIVERED
            ) {
                throw new \Exception('配達完了した配送の状態は変更できません');
            }

            $shipment->update($validated);

            // 注文のステータスも更新
            $order = $shipment->order;
            if (isset($validated['status'])) {
                switch ($validated['status']) {
                    case Shipment::STATUS_SHIPPED:
                        $order->update(['status' => Order::STATUS_SHIPPED]);
                        break;
                    case Shipment::STATUS_DELIVERED:
                        $order->update(['status' => Order::STATUS_DELIVERED]);
                        break;
                }
            }

            DB::commit();

            return response()->json(
                $shipment->load(['order.customer']),
                Response::HTTP_OK
            );
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        }
    }

    /**
     * 指定された配送情報を削除
     */
    public function destroy(Shipment $shipment): JsonResponse
    {
        try {
            DB::beginTransaction();

            // 配送済みの場合は削除できない
            if (in_array($shipment->status, [
                Shipment::STATUS_SHIPPED,
                Shipment::STATUS_IN_TRANSIT,
                Shipment::STATUS_DELIVERED
            ])) {
                throw new \Exception('配送処理が開始された配送情報は削除できません');
            }

            $shipment->delete();

            DB::commit();
            return response()->json(null, Response::HTTP_NO_CONTENT);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        }
    }
}
