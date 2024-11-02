<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Campaign;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class CampaignController extends Controller
{
    /**
     * キャンペーン一覧を取得
     */
    public function index(Request $request): JsonResponse
    {
        $query = Campaign::query();

        // 有効期間による絞り込み
        if ($request->has('start_date')) {
            $query->where('startDate', '>=', $request->start_date);
        }
        if ($request->has('end_date')) {
            $query->where('endDate', '<=', $request->end_date);
        }

        // キャンペーン名による検索
        if ($request->has('name')) {
            $query->where('name', 'like', '%' . $request->name . '%');
        }

        // 割引コードによる検索
        if ($request->has('discount_code')) {
            $query->where('discountCode', $request->discount_code);
        }

        // 割引率による絞り込み
        if ($request->has('min_discount_rate')) {
            $query->where('discountRate', '>=', $request->min_discount_rate);
        }
        if ($request->has('max_discount_rate')) {
            $query->where('discountRate', '<=', $request->max_discount_rate);
        }

        // アクティブ状態による絞り込み
        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        // 現在有効なキャンペーンのみ取得
        if ($request->boolean('current_only')) {
            $today = now()->startOfDay();
            $query->where('startDate', '<=', $today)
                ->where('endDate', '>=', $today)
                ->where('is_active', true);
        }

        // ソート順の設定
        $sortField = $request->input('sort_by', 'startDate');
        $sortOrder = $request->input('sort_order', 'desc');
        $allowedSortFields = ['name', 'startDate', 'endDate', 'discountRate'];

        if (in_array($sortField, $allowedSortFields)) {
            $query->orderBy($sortField, $sortOrder);
        }

        $perPage = $request->input('per_page', 15);
        $campaigns = $query->paginate($perPage);

        return response()->json($campaigns, Response::HTTP_OK);
    }

    /**
     * 新規キャンペーンを作成
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'startDate' => 'required|date|after_or_equal:today',
            'endDate' => 'required|date|after:startDate',
            'discountRate' => 'required|integer|min:1|max:100',
            'discountCode' => 'required|string|max:50|unique]campaigns',
            'description' => 'nullable|string',
            'is_active' => 'boolean'
        ]);

        try {
            DB::beginTransaction();

            $campaign = Campaign::create($validated);

            DB::commit();

            return response()->json($campaign, Response::HTTP_CREATED);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'キャンペーンの作成に失敗しました。'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * 指定されたキャンペーンの詳細を取得
     */
    public function show(Campaign $campaign): JsonResponse
    {
        // キャンペーンに関連する注文も取得
        $campaign->load('orders');

        // キャンペーンの現在の状態を確認
        $isCurrentlyActive = $campaign->isValid();

        // レスポンスデータの準備
        $responseData = array_merge($campaign->toArray(), [
            'isCurrentlyActive' => $isCurrentlyActive,
            'ordersCount' => $campaign->orders->count(),
            'totalDiscountAmount' => $campaign->orders->sum('discountApplied')
        ]);

        return response()->json($responseData, Response::HTTP_OK);
    }

    /**
     * 指定されたキャンペーンを更新
     */
    public function update(Request $request, Campaign $campaign): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:100',
            'startDate' => 'sometimes|required|date',
            'endDate' => 'sometimes|required|date|after:startDate',
            'discountRate' => 'sometimes|required|integer|min:1|max:100',
            'discountCode' => 'sometimes|required|string|max:50|unique]campaigns,discountCode,' . $campaign->id,
            'description' => 'nullable|string',
            'is_active' => 'sometimes|required|boolean'
        ]);

        try {
            DB::beginTransaction();

            // キャンペーンが既に使用されている場合、割引率の変更を制限
            if (
                $campaign->orders()->exists() &&
                isset($validated['discountRate']) &&
                $campaign->discountRate != $validated['discountRate']
            ) {
                throw new \Exception('使用済みのキャンペーンの割引率は変更できません');
            }

            $campaign->update($validated);

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

            // 使用中のキャンペーンは削除できない
            if ($campaign->orders()->exists()) {
                throw new \Exception('使用中のキャンペーンは削除できません');
            }

            $campaign->delete();

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

            $campaign->update([
                'is_active' => !$campaign->is_active
            ]);

            DB::commit();

            return response()->json($campaign, Response::HTTP_OK);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'ステータスの更新に失敗しました'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
