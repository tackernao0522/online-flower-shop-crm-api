<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreCustomerRequest;
use App\Http\Requests\Api\V1\UpdateCustomerRequest;
use App\Models\Customer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Events\CustomerCountUpdated;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Cache;

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

            $validated = $request->validate([
                'search' => 'nullable|string|max:255',
            ]);

            if ($request->has('search')) {
                $searchTerm = filter_var($validated['search'], FILTER_SANITIZE_SPECIAL_CHARS);
                Log::info('検索クエリ: ' . $searchTerm);
                $query = Customer::searchByTerm($query, $searchTerm);
            }

            $customers = $query->paginate(20);
            $totalCount = Customer::count();

            $previousTotalCount = Cache::get('previous_total_count', $totalCount);
            $changeRate = Cache::get('change_rate', 0);

            if ($totalCount !== $previousTotalCount) {
                $changeRate = $this->calculateChangeRate($totalCount, $previousTotalCount);
                Cache::put('previous_total_count', $totalCount, now()->addDay());
                Cache::put('change_rate', $changeRate, now()->addDay());
            }

            event(new CustomerCountUpdated($totalCount, $previousTotalCount, $changeRate));

            Log::info("顧客一覧を取得しました。総数: {$totalCount}, 前回の総数: {$previousTotalCount}, 変化率: {$changeRate}%");

            return response()->json([
                'data' => $customers->map(function ($customer) {
                    $customer->phoneNumber = $this->formatPhoneNumber($customer->phoneNumber);
                    return $customer;
                }),
                'meta' => [
                    'currentPage' => $customers->currentPage(),
                    'totalPages' => $customers->lastPage(),
                    'totalCount' => $totalCount,
                    'previousTotalCount' => $previousTotalCount,
                    'changeRate' => $changeRate,
                ]
            ]);
        } catch (QueryException $e) {
            Log::error('データベースエラーが発生しました: ' . $e->getMessage(), ['exception' => $e]);
            return response()->json([
                'error' => [
                    'code' => 'DATABASE_ERROR',
                    'message' => 'データベース操作中にエラーが発生しました。',
                ]
            ], 500);
        } catch (\Exception $e) {
            Log::error('エラーが発生しました: ' . $e->getMessage(), ['exception' => $e]);
            return response()->json([
                'error' => [
                    'code' => 'SERVER_ERROR',
                    'message' => '予期せぬエラーが発生しました。',
                ]
            ], 500);
        }
    }

    public function store(StoreCustomerRequest $request)
    {
        try {
            $previousTotalCount = Customer::count();
            $customer = Customer::create($request->validated());
            $totalCount = Customer::count();

            $changeRate = $this->calculateChangeRate($totalCount, $previousTotalCount);

            Cache::put('previous_total_count', $totalCount, now()->addDay());
            Cache::put('change_rate', $changeRate, now()->addDay());

            event(new CustomerCountUpdated($totalCount, $previousTotalCount, $changeRate));

            Log::info("新しい顧客が作成されました。ID: {$customer->id}, 総数: {$totalCount}, 前回の総数: {$previousTotalCount}, 変化率: {$changeRate}%");

            return response()->json($customer, 201);
        } catch (QueryException $e) {
            Log::error('顧客作成中にデータベースエラーが発生しました: ' . $e->getMessage(), ['exception' => $e]);
            return response()->json([
                'error' => [
                    'code' => 'DATABASE_ERROR',
                    'message' => '顧客の作成中にエラーが発生しました。',
                ]
            ], 500);
        } catch (\Exception $e) {
            Log::error('顧客作成中にエラーが発生しました: ' . $e->getMessage(), ['exception' => $e]);
            return response()->json([
                'error' => [
                    'code' => 'SERVER_ERROR',
                    'message' => '顧客の作成中に予期せぬエラーが発生しました。',
                ]
            ], 500);
        }
    }

    public function show(Customer $customer)
    {
        try {
            $customer->phoneNumber = $this->formatPhoneNumber($customer->phoneNumber);
            return response()->json($customer);
        } catch (\Exception $e) {
            Log::error('顧客情報の取得中にエラーが発生しました: ' . $e->getMessage(), ['exception' => $e]);
            return response()->json([
                'error' => [
                    'code' => 'SERVER_ERROR',
                    'message' => '顧客情報の取得中にエラーが発生しました。',
                ]
            ], 500);
        }
    }

    public function update(UpdateCustomerRequest $request, Customer $customer)
    {
        try {
            $customer->update($request->validated());
            $updatedCustomer = $customer->fresh();

            Log::info('顧客情報が更新されました。ID: ' . $customer->id);

            return response()->json([
                'id' => $updatedCustomer->id,
                'name' => $updatedCustomer->name,
                'email' => $updatedCustomer->email,
                'phoneNumber' => $updatedCustomer->phoneNumber,
                'address' => $updatedCustomer->address,
                'birthDate' => $updatedCustomer->birthDate->format('Y-m-d'),
                'updatedAt' => $updatedCustomer->updated_at->toIso8601String(),
            ]);
        } catch (QueryException $e) {
            Log::error('顧客更新中にデータベースエラーが発生しました: ' . $e->getMessage(), ['exception' => $e]);
            return response()->json([
                'error' => [
                    'code' => 'DATABASE_ERROR',
                    'message' => '顧客情報の更新中にエラーが発生しました。',
                ]
            ], 500);
        } catch (\Exception $e) {
            Log::error('顧客更新中にエラーが発生しました: ' . $e->getMessage(), ['exception' => $e]);
            return response()->json([
                'error' => [
                    'code' => 'SERVER_ERROR',
                    'message' => '顧客情報の更新中に予期せぬエラーが発生しました。',
                ]
            ], 500);
        }
    }

    public function destroy(Customer $customer)
    {
        try {
            $previousTotalCount = Customer::count();
            $customer->delete();
            $totalCount = $previousTotalCount - 1;

            $changeRate = $this->calculateChangeRate($totalCount, $previousTotalCount);

            Cache::put('previous_total_count', $totalCount, now()->addDay());
            Cache::put('change_rate', $changeRate, now()->addDay());

            event(new CustomerCountUpdated($totalCount, $previousTotalCount, $changeRate));

            Log::info("顧客が削除されました。ID: {$customer->id}, 総数: {$totalCount}, 前回の総数: {$previousTotalCount}, 変化率: {$changeRate}%");

            return response()->json(null, 204);
        } catch (QueryException $e) {
            Log::error('顧客削除中にデータベースエラーが発生しました: ' . $e->getMessage(), ['exception' => $e]);
            return response()->json([
                'error' => [
                    'code' => 'DATABASE_ERROR',
                    'message' => '顧客の削除中にエラーが発生しました。',
                ]
            ], 500);
        } catch (\Exception $e) {
            Log::error('顧客削除中にエラーが発生しました: ' . $e->getMessage(), ['exception' => $e]);
            return response()->json([
                'error' => [
                    'code' => 'SERVER_ERROR',
                    'message' => '顧客の削除中に予期せぬエラーが発生しました。',
                ]
            ], 500);
        }
    }

    private function calculateChangeRate($currentCount, $previousCount)
    {
        if ($previousCount == 0) {
            return $currentCount > 0 ? 100 : 0;
        }
        $changeRate = (($currentCount - $previousCount) / $previousCount) * 100;
        return round($changeRate, 2);
    }

    // private function sanitizePhoneNumber($phoneNumber)
    // {
    //     return str_replace('-', '', $phoneNumber);
    // }

    private function formatPhoneNumber($phoneNumber)
    {
        return preg_replace('/(\d{3})(\d{4})(\d{4})/', '$1-$2-$3', $phoneNumber);
    }
}
