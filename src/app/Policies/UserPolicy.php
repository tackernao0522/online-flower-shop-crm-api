<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Auth\Access\Response;

class UserPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $user->isAdmin() || $user->isManager();
    }

    public function view(User $user, User $model): bool
    {
        return $user->id === $model->id || $user->isAdmin() || $user->isManager();
    }

    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, User $model): bool
    {
        return $user->id === $model->id || $user->isAdmin();
    }

    public function delete(User $user, User $model): Response
    {
        if (!$user->isAdmin()) {
            return Response::deny('管理者のみがユーザーを削除できます。');
        }
        if ($user->id === $model->id) {
            return Response::deny('自分自身を削除することはできません。');
        }
        return Response::allow();
    }

    public function changePassword(User $user, User $model): bool
    {
        return $user->id === $model->id || $user->isAdmin();
    }

    public function restore(User $user, User $model): Response
    {
        if (!$user->isAdmin()) {
            return Response::deny('管理者のみが削除されたユーザーを復元できます。');
        }
        return Response::allow();
    }

    public function forceDelete(User $user, User $model): bool
    {
        return $user->isAdmin() && $user->id !== $model->id;
    }

    public function changeRole(User $user, User $model): bool
    {
        return $user->isAdmin() && $user->id !== $model->id;
    }
}
