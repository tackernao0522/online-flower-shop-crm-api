<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreCustomerRequest;
use App\Http\Requests\Api\V1\UpdateCustomerRequest;
use App\Models\Customer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class CustomerController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:api');
    }

    public function index(Request $request)
    {
        try {
            $query = Customer::query();

            // バリデーションの追加
            $validated = $request->validate([
                'search' => 'nullable|string|max:255', // search パラメータのバリデーション
            ]);

            // サニタイズ処理の追加
            if ($request->has('search')) {
                $searchTerm = $validated['search'];
                // FILTER_SANITIZE_SPECIAL_CHARS でサニタイズ
                $searchTerm = filter_var($searchTerm, FILTER_SANITIZE_SPECIAL_CHARS);

                // デバッグログの追加
                Log::info('検索クエリ: ' . $searchTerm);

                // 検索クエリを実行
                $query = Customer::searchByTerm($query, $searchTerm);
            }

            $customers = $query->paginate(20);

            return response()->json([
                'data' => $customers->map(function ($customer) {
                    // ハイフン付きの電話番号で返す
                    $customer->phoneNumber = $this->formatPhoneNumber($customer->phoneNumber);
                    return $customer;
                }),
                'meta' => [
                    'currentPage' => $customers->currentPage(),
                    'totalPages' => $customers->lastPage(),
                    'totalCount' => $customers->total(),
                ]
            ]);
        } catch (\Exception $e) {
            // エラーメッセージに追加情報を含める
            Log::error('エラーが発生しました: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);

            return response()->json([
                'error' => [
                    'code' => 'SERVER_ERROR',
                    'message' => $e->getMessage(),  // 詳細なエラーメッセージ
                ]
            ], 500);
        }
    }

    public function store(StoreCustomerRequest $request)
    {
        // 電話番号をそのまま保存（ハイフン付き）
        $customer = Customer::create($request->validated());

        return response()->json($customer, 201);
    }

    public function show(Customer $customer)
    {
        // ハイフン付きの電話番号で返す
        $customer->phoneNumber = $this->formatPhoneNumber($customer->phoneNumber);
        return response()->json($customer);
    }

    public function update(UpdateCustomerRequest $request, Customer $customer)
    {
        $customer->update($request->validated());

        $updatedCustomer = $customer->fresh();

        return response()->json([
            'id' => $updatedCustomer->id,
            'name' => $updatedCustomer->name,
            'email' => $updatedCustomer->email,
            'phoneNumber' => $updatedCustomer->phoneNumber,
            'address' => $updatedCustomer->address,
            'birthDate' => $updatedCustomer->birthDate->format('Y-m-d'),
            'updatedAt' => $updatedCustomer->updated_at->toIso8601String(),
        ]);
    }

    public function destroy(Customer $customer)
    {
        $customer->delete();
        return response()->json(null, 204);
    }

    // ハイフンを除去するメソッド
    private function sanitizePhoneNumber($phoneNumber)
    {
        return str_replace('-', '', $phoneNumber);
    }

    // ハイフン付きの電話番号にフォーマットするメソッド
    private function formatPhoneNumber($phoneNumber)
    {
        // 09012345678 → 090-1234-5678 の形式に変換
        return preg_replace('/(\d{3})(\d{4})(\d{4})/', '$1-$2-$3', $phoneNumber);
    }
}
