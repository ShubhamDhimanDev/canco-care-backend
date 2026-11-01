<?php

namespace App\Policies;

use App\Models\Story;
use App\Models\User;

/**
 * Both roles (admin and editor) manage stories; guests never reach these checks
 * because the admin routes sit behind the `auth` middleware.
 */
class StoryPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Story $story): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Story $story): bool
    {
        return true;
    }

    public function delete(User $user, Story $story): bool
    {
        return true;
    }

    public function restore(User $user, Story $story): bool
    {
        return true;
    }

    /** Permanent deletion is administrator-only. */
    public function forceDelete(User $user, Story $story): bool
    {
        return $user->isAdmin();
    }
}
