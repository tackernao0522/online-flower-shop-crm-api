<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreShipmentRequest;
use App\Http\Requests\Api\V1\UpdateShipmentRequest;
use App\Models\Shipment;
use App\Models\Order;
use App\Services\ShipmentService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class ShipmentController extends Controller
{
    private $shipmentService;

    public function __construct(ShipmentService $shipmentService)
    {
        $this->shipmentService = $shipmentService;
    }

    /**
     * 配送一覧を取得
     */
    public function index(Request $request): JsonResponse
    {
        $query = Shipment::with(['order.customer'])
            ->when($request->start_date || $request->end_date, function ($query) use ($request) {
                return $query->dateRange($request->start_date, $request->end_date);
            })
            ->when($request->status, function ($query) use ($request) {
                return $query->withStatus($request->status);
            })
            ->when($request->tracking_number, function ($query) use ($request) {
                return $query->trackingNumberLike($request->tracking_number);
            })
            ->when($request->order_number, function ($query) use ($request) {
                return $query->orderNumberLike($request->order_number);
            })
            ->when($request->customer_name, function ($query) use ($request) {
                return $query->customerNameLike($request->customer_name);
            })
            ->orderBy(
                $request->input('sort_by', 'shippingDate'),
                $request->input('sort_order', 'desc')
            );

        $shipments = $query->paginate($request->input('per_page', 15));

        return response()->json($shipments, Response::HTTP_OK);
    }

    /**
     * 新規配送情報を作成
     */
    public function store(StoreShipmentRequest $request): JsonResponse
    {
        try {
            DB::beginTransaction();

            $shipment = $this->shipmentService->createShipment($request->validated());

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
    public function update(UpdateShipmentRequest $request, Shipment $shipment): JsonResponse
    {
        try {
            DB::beginTransaction();

            $shipment = $this->shipmentService->UpdateShipment($shipment, $request->validated());

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

            $this->shipmentService->deleteShipment($shipment);

            DB::commit();
            return response()->json(null, Response::HTTP_NO_CONTENT);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        }
    }
}
