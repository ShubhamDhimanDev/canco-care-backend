<?php

namespace App\Policies;

use App\Models\Story;
use App\Models\User;

/**
 * Stories are governed by the `stories.*` permissions (see PermissionCatalog). Super administrators
 * pass through Gate::before; guests never reach these checks because the admin routes sit behind `auth`.
 */
class StoryPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('stories.view');
    }

    public function view(User $user, Story $story): bool
    {
        return $user->can('stories.view');
    }

    public function create(User $user): bool
    {
        return $user->can('stories.create');
    }

    public function update(User $user, Story $story): bool
    {
        return $user->can('stories.update');
    }

    public function delete(User $user, Story $story): bool
    {
        return $user->can('stories.delete');
    }

    public function restore(User $user, Story $story): bool
    {
        return $user->can('stories.delete');
    }

    public function forceDelete(User $user, Story $story): bool
    {
        return $user->can('stories.force-delete');
    }
}
