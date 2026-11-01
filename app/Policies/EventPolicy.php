<?php

namespace App\Policies;

use App\Models\Event;
use App\Models\User;

/**
 * Both roles (admin and editor) manage events; permanent deletion is admin-only.
 */
class EventPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Event $event): bool
    {
        return true;
    }

    public function delete(User $user, Event $event): bool
    {
        return true;
    }

    public function restore(User $user, Event $event): bool
    {
        return true;
    }

    public function forceDelete(User $user, Event $event): bool
    {
        return $user->isAdmin();
    }
}
