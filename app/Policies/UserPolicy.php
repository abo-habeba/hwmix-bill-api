<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class UserPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $authenticatedUser)
    {
        if (
            $authenticatedUser->hasPermissionTo('users.show') ||
            $authenticatedUser->hasPermissionTo('employee') ||
            $authenticatedUser->type === 'company_owner'
        ) {
            return true;
        }
        return response()->json([
            'message' => 'Unauthorized access.',
        ], 403);
    }

    public function create(User $authUser)
    {
        if ($authUser->hasPermissionTo('users.create')) {
            return true;
        }
        return response()->json([
            'message' => 'Unauthorized access.',
        ], 403);
    }

    public function update(User $authUser, User $user)
    {
        if ($authUser->hasPermissionTo('users.update')) {
            return true;
        }
        return $authUser->id === $user->id && $authUser->hasPermissionTo('users.update.own');
    }

    public function delete(User $authUser, User $user)
    {
        if ($authUser->hasPermissionTo('users.delete')) {
            return true;
        }
        return $authUser->id === $user->id && $authUser->hasPermissionTo('users.delete.own');
    }

    public function viewOwn(User $authUser, User $user)
    {
        return $authUser->id === $user->id && $authUser->hasPermissionTo('users.view.own');
    }

    public function updateOwn(User $authUser, User $user)
    {
        return $authUser->id === $user->id && $authUser->hasPermissionTo('users.update.own');
    }

    public function deleteOwn(User $authUser, User $user)
    {
        return $authUser->id === $user->id && $authUser->hasPermissionTo('users.delete.own');
    }
}
