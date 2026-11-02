<?php

namespace App\Policies;

use App\Models\Media;
use App\Models\User;

/**
 * Both roles (admin and editor) manage the media library. Whether a file can be deleted is decided
 * by MediaUsage (a file in use stays), not by role.
 */
class MediaPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Media $media): bool
    {
        return true;
    }

    public function delete(User $user, Media $media): bool
    {
        return true;
    }
}
