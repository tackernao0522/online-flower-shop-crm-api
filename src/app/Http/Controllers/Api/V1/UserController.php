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

            $users = User::query();

            $users = $this->applySorting($request, $users);
            $users = $this->applyFiltering($request, $users);
            $users = $this->applyPagination($request, $users);

            if ($users->isEmpty()) {
                throw new ModelNotFoundException('ユーザーが見つかりません。');
            }

            return response()->json([
                'data' => $users->items(),
                'meta' => [
                    'currentPage' => $users->currentPage(),
                    'totalPages' => $users->lastPage(),
                    'totalCount' => $users->total()
                ]
            ]);
        } catch (ModelNotFoundException $e) {
            \Log::debug('ModelNotFoundException caught in index method: ' . $e->getMessage());
            return $this->errorResponse($e, 404, 'RESOURCE_NOT_FOUND', '指定されたユーザーが見つかりません。');
        } catch (\Exception $e) {
            \Log::debug('Exception caught in index method: ' . $e->getMessage());
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
            \Log::debug('AuthorizationException caught in store method');
            return $this->errorResponse($e, 403, 'UNAUTHORIZED', 'このアクションを実行する権限がありません。');
        } catch (\Exception $e) {
            \Log::debug('Exception caught in store method: ' . $e->getMessage());
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
            \Log::error('Error deleting user: ' . $e->getMessage());
            return $this->errorResponse($e);
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
        if ($request->has('role')) {
            $validRoles = ['ADMIN', 'MANAGER', 'STAFF'];
            $role = $request->input('role');

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
            $status = 404; // 189行目
            $code = 'RESOURCE_NOT_FOUND';
            $message = '指定されたユーザーが見つかりません。'; // 191行目
        } elseif ($e instanceof \InvalidArgumentException) {
            $status = 400;
            $code = 'INVALID_PARAMETER';
            $message = $e->getMessage();
        }

        \Log::error('User operation failed: ' . $e->getMessage());
        return response()->json([
            'error' => [
                'code' => $code,
                'message' => $message
            ]
        ], $status);
    }
}
