<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use App\Http\Requests\Api\V1\StoreUserRequest;
use App\Http\Requests\Api\V1\UpdateUserRequest;
use Illuminate\Support\Facades\Hash;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Log;

class UserController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:api');
    }

    public function index(Request $request)
    {
        try {
            $this->authorize('viewAny', User::class);

            // 1. リクエストパラメータのログ
            Log::debug('User index request parameters:', $request->all());

            $users = User::query();

            // ソートとフィルタリングの適用
            $users = $this->applySorting($request, $users);
            $users = $this->applyFiltering($request, $users);

            // 2. フィルタリングとソート後のクエリログ
            Log::debug('Query after sorting and filtering:', ['sql' => $users->toSql(), 'bindings' => $users->getBindings()]);

            // 検索条件の適用 (ユーザー名またはメールアドレスでの検索)
            if ($request->has('search') && !empty($request->input('search'))) {
                $searchTerm = $request->input('search');
                $users->where(function ($query) use ($searchTerm) {
                    $query->where('username', 'LIKE', "%{$searchTerm}%")
                        ->orWhere('email', 'LIKE', "%{$searchTerm}%");
                });

                // 3. 検索条件適用後のクエリログ
                Log::debug('Query after applying search:', ['sql' => $users->toSql(), 'bindings' => $users->getBindings()]);
            }

            // 4. ページネーション適用直前のクエリログ
            Log::debug('Final query before pagination:', ['sql' => $users->toSql(), 'bindings' => $users->getBindings()]);

            // ページネーションの適用
            $users = $this->applyPagination($request, $users);

            // 5. レスポンスデータのログ
            Log::debug('User index response data:', [
                'itemsCount' => count($users->items()),
                'currentPage' => $users->currentPage(),
                'totalPages' => $users->lastPage(),
                'totalCount' => $users->total()
            ]);

            return response()->json([
                'data' => $users->items(),
                'meta' => [
                    'currentPage' => $users->currentPage(),
                    'totalPages' => $users->lastPage(),
                    'totalCount' => $users->total()
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('User operation failed: ' . $e->getMessage());
            return $this->errorResponse($e);
        }
    }

    public function store(StoreUserRequest $request)
    {
        try {
            $this->authorize('create', User::class);

            $validated = $request->validated();
            $validated['password'] = Hash::make($validated['password']);

            $user = User::create($validated);

            return response()->json([
                'id' => $user->id,
                'username' => $user->username,
                'email' => $user->email,
                'role' => $user->role,
                'createdAt' => $user->created_at
            ], 201);
        } catch (AuthorizationException $e) {
            Log::debug('AuthorizationException caught in store method');
            return $this->errorResponse($e, 403, 'UNAUTHORIZED', 'このアクションを実行する権限がありません。');
        } catch (\Exception $e) {
            Log::debug('Exception caught in store method: ' . $e->getMessage());
            return $this->errorResponse($e);
        }
    }

    public function show(User $user)
    {
        try {
            $this->authorize('view', $user);

            return response()->json([
                'id' => $user->id,
                'username' => $user->username,
                'email' => $user->email,
                'role' => $user->role,
                'lastLoginDate' => $user->last_login_date,
                'createdAt' => $user->created_at,
                'updatedAt' => $user->updated_at
            ]);
        } catch (\Exception $e) {
            return $this->errorResponse($e);
        }
    }

    public function update(UpdateUserRequest $request, User $user)
    {
        try {
            $this->authorize('update', $user);

            $validated = $request->validated();
            if (isset($validated['password'])) {
                $validated['password'] = Hash::make($validated['password']);
            }

            $user->update($validated);

            return response()->json([
                'id' => $user->id,
                'username' => $user->username,
                'email' => $user->email,
                'role' => $user->role,
                'updatedAt' => $user->updated_at
            ]);
        } catch (\Exception $e) {
            return $this->errorResponse($e);
        }
    }

    public function destroy(User $user)
    {
        try {
            $this->authorize('delete', $user);
            $user->delete();

            return response()->json(null, 204);
        } catch (\Exception $e) {
            Log::error('Error deleting user: ' . $e->getMessage());
            return $this->errorResponse($e);
        }
    }

    public function getCurrentUserOnlineStatus(Request $request)
    {
        $user = $request->user();
        return response()->json(['is_online' => $user->is_online]);
    }

    public function updateOnlineStatus(Request $request)
    {
        $user = $request->user();
        $user->update(['is_online' => $request->input('is_online', true)]);
        return response()->json(['message' => 'オンラインステータスが更新されました。']);
    }

    public function getOnlineStatus(User $user)
    {
        try {
            Log::info("getOnlineStatus called for user: {$user->id}");
            Log::info("User online status: " . ($user->is_online ? '1' : '0'));
            Log::info("Last activity: " . $user->last_activity);
            return response()->json(['is_online' => $user->is_online]);
        } catch (\Exception $e) {
            Log::error('Error in getOnlineStatus: ' . $e->getMessage());
            Log::error('Stack trace: ' . $e->getTraceAsString());
            return response()->json(['error' => 'An error occurred while fetching the online status'], 500);
        }
    }

    private function applySorting(Request $request, $query)
    {
        if ($request->has('sort')) {
            $sortField = $request->input('sort');
            $direction = $sortField[0] === '-' ? 'desc' : 'asc';
            $sortField = ltrim($sortField, '-');

            $validSortFields = ['username', 'email', 'role', 'created_at', 'last_login_date'];
            if (!in_array($sortField, $validSortFields)) {
                throw new \InvalidArgumentException('無効なソートパラメータです。');
            }

            $query->orderBy($sortField, $direction);
        }

        return $query;
    }

    private function applyFiltering(Request $request, $query)
    {
        if ($request->has('role') && !empty($request->input('role'))) {
            $validRoles = ['ADMIN', 'MANAGER', 'STAFF'];
            $role = strtoupper($request->input('role'));

            if (!in_array($role, $validRoles)) {
                throw new \InvalidArgumentException('無効な役割パラメータです。');
            }

            $query->where('role', $role);
        }

        return $query;
    }

    private function applyPagination(Request $request, $query)
    {
        $limit = $request->input('limit', 20);

        if (!is_numeric($limit) || $limit < 1 || $limit > 100) {
            throw new \InvalidArgumentException('無効な limit パラメータです。');
        }

        return $query->paginate($limit);
    }

    private function errorResponse(\Exception $e, $status = 500, $code = 'SERVER_ERROR', $message = 'ユーザー操作に失敗しました。')
    {
        if ($e instanceof AuthorizationException) {
            $status = 403;
            $code = 'UNAUTHORIZED';
            $message = 'このアクションを実行する権限がありません。';
        } elseif ($e instanceof ModelNotFoundException) {
            $status = 404;
            $code = 'RESOURCE_NOT_FOUND';
            $message = '指定されたユーザーが見つかりません。';
        } elseif ($e instanceof \InvalidArgumentException) {
            $status = 400;
            $code = 'INVALID_PARAMETER';
            $message = $e->getMessage();
        }

        Log::error('User operation failed: ' . $e->getMessage());
        return response()->json([
            'error' => [
                'code' => $code,
                'message' => $message
            ]
        ], $status);
    }
}
