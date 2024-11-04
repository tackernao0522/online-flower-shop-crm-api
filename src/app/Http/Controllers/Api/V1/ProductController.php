<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Services\ProductService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class ProductController extends Controller
{
    private $productServece;

    public function __construct(ProductService $productService)
    {
        $this->productServece = $productService;
    }

    /**
     * 商品一覧を取得 (注文管理などでの参照用)
     */
    public function index(Request $request): JsonResponse
    {
        $products = $this->productServece->getProducts(
            [
                'category' => $request->category,
            ],
            $request->input('per_page', 15)
        );

        return response()->json($products, Response::HTTP_OK);
    }

    /**
     * 指定された商品の詳細を取得
     */
    public function show(Product $product): JsonResponse
    {
        try {
            $product = $this->productServece->getProductDetails($product);
            return response()->json($product, Response::HTTP_OK);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], Response::HTTP_NOT_FOUND);
        }
    }

    /**
     * 在庫状況を確認
     */
    public function checkStock(Product $product): JsonResponse
    {
        try {
            $stockStatus = $this->productServece->getStockStatus($product);
            return response()->json($stockStatus, Response::HTTP_OK);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], Response::HTTP_NOT_FOUND);
        }
    }
}
