<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreCampaignRequest;
use App\Http\Requests\Api\V1\UpdateCampaignReqeust;
use App\Models\Campaign;
use App\Services\CampaignService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class CampaignController extends Controller
{
    private $campaignService;

    public function __construct(CampaignService $campaignService)
    {
        $this->campaignService = $campaignService;
    }

    /**
     * キャンペーン一覧を取得
     */
    public function index(Request $request): JsonResponse
    {
        // リクエストパラメータを明示的に取得
        $isActive = $request->has('is_active') ? $request->boolean('is_active') : null;

        Log::info('Campaign index params:', [
            'params' => $request->all(),
            'is_active' => $isActive
        ]);

        $query = Campaign::query()
            ->dateRange($request->start_date, $request->end_date)
            ->nameLike($request->name)
            ->discountCode($request->discount_code)
            ->discountRateRange($request->min_discount_rate, $request->max_discount_rate)
            ->active($isActive);  // null を渡すことでフィルタリングを回避

        Log::info('Campaign query initial:', [
            'sql' => $query->toSql(),
            'bindings' => $query->getBindings(),
            'count' => $query->count()
        ]);

        if ($request->boolean('current_only')) {
            $query->currentlyValid();
        }

        $sortField = $request->input('sort_by', 'startDate');
        $sortOrder = $request->input('sort_order', 'desc');
        $allowedSortFields = ['name', 'startDate', 'endDate', 'discountRate'];

        if (in_array($sortField, $allowedSortFields)) {
            $query->orderBy($sortField, $sortOrder);
        }

        $campaigns = $query->paginate($request->input('per_page', 15));

        Log::info('Campaign index result:', [
            'total_count' => $campaigns->total(),
            'filtered_count' => $campaigns->count()
        ]);

        return response()->json($campaigns, Response::HTTP_OK);
    }

    /**
     * 新規キャンペーンを作成
     */
    public function store(StoreCampaignRequest $request): JsonResponse
    {
        try {
            DB::beginTransaction();

            $campaign = $this->campaignService->createCampaign($request->validated());

            DB::commit();

            return response()->json($campaign, Response::HTTP_CREATED);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'キャンペーンの作成に失敗しました'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * 指定されたキャンペーンの詳細を取得
     */
    public function show(Campaign $campaign): JsonResponse
    {
        $campaignDetails = $this->campaignService->getCampaignDetails($campaign);
        return response()->json($campaignDetails, Response::HTTP_OK);
    }

    /**
     * 指定されたキャンペーンを更新
     */
    public function update(UpdateCampaignReqeust $request, Campaign $campaign): JsonResponse
    {
        try {
            DB::beginTransaction();

            $campaign = $this->campaignService->updateCampaign($campaign, $request->validated());

            DB::commit();

            return response()->json($campaign, Response::HTTP_OK);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        }
    }

    /**
     * 指定されたキャンペーンを削除
     */
    public function destroy(Campaign $campaign): JsonResponse
    {
        try {
            DB::beginTransaction();

            $this->campaignService->deleteCampaign($campaign);

            DB::commit();
            return response()->json(null, Response::HTTP_NO_CONTENT);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        }
    }

    /**
     * キャンペーンの有効/無効を切り替え
     */
    public function toggleStatus(Campaign $campaign): JsonResponse
    {
        try {
            DB::beginTransaction();

            $campaign = $this->campaignService->toggleStatus($campaign);

            DB::commit();

            return response()->json($campaign, Response::HTTP_OK);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'ステータスの更新に失敗しました'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
