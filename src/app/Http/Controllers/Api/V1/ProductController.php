<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class ProductController extends Controller
{
    /**
     * 商品一覧を取得 (注文管理などでの参照用)
     */
    public function index(Request $request): JsonResponse
    {
        $query = Product::query();

        // カテゴリーでフィルタリング
        if ($request->has('category')) {
            $query->where('category', $request->category);
        }

        // アクティブな商品のみ表示
        $query->where('is_active', true);

        // ページネーション
        $perPage = $request->input('per_page', 15);
        $products = $query->paginate($perPage);

        return response()->json($products, Response::HTTP_OK);
    }

    /**
     * 指定された商品の詳細を取得
     */
    public function show(Product $product): JsonResponse
    {
        // アクティブな商品のみ表示
        if (!$product->is_active) {
            return response()->json(['message' => '指定された商品は現在利用できません'], Response::HTTP_NOT_FOUND);
        }

        return response()->json($product, Response::HTTP_OK);
    }

    /**
     * 在庫状況を確認
     */
    public function checkStock(Product $product): JsonResponse
    {
        if (!$product->is_active) {
            return response()->json(['message' => '指定された商品は現在利用できません'], Response::HTTP_NOT_FOUND);
        }

        return response()->json([
            'id' => $product->id,
            'name' => $product->name,
            'stockQuantity' => $product->stockQuantity,
            'is_in_stock' => $product->stockQuantity > 0
        ], Response::HTTP_OK);
    }
}
