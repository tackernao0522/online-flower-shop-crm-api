<?php

namespace App\Services;

use App\Models\Product;

class ProductService
{
    /**
     * 商品一覧を取得
     */
    public function getProducts(array $filters, int $perPage = 15)
    {
        return Product::query()
            ->withCategory($filters['category'] ?? null)
            ->active()
            ->paginate($perPage);
    }

    /**
     * 商品詳細を取得
     */
    public function getProductDetails(Product $product): ?Product
    {
        if (!$product->is_active) {
            throw new \Exception('指定された商品は現在利用できません');
        }

        return $product;
    }

    /**
     * 在庫状況を取得
     */
    public function getStockStatus(Product $product): array
    {
        if (!$product->is_active) {
            throw new \Exception('指定された商品は現在利用できません');
        }

        return [
            'id' => $product->id,
            'name' => $product->name,
            'stockQuantity' => $product->stockQuantity,
            'is_in_stock' => $product->stockQuantity > 0
        ];
    }
}
