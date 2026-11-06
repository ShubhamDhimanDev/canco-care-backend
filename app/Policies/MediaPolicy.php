<?php

namespace App\Policies;

use App\Models\Media;
use App\Models\User;

/**
 * The media library is governed by the `media.*` permissions (see PermissionCatalog). Whether a file can
 * be deleted is also decided by MediaUsage (a file in use stays), not only by permission.
 *
 * `pick` and `upload` are what the picker inside the story, event and home-page forms needs: anyone who
 * may edit one of those can choose and add pictures there without being given the whole library screen.
 */
class MediaPolicy
{
    private const EDITING = [
        'stories.create', 'stories.update', 'events.create', 'events.update', 'home-page.update',
    ];

    public function viewAny(User $user): bool
    {
        return $user->can('media.view');
    }

    public function create(User $user): bool
    {
        return $user->can('media.create');
    }

    public function update(User $user, Media $media): bool
    {
        return $user->can('media.update');
    }

    public function delete(User $user, Media $media): bool
    {
        return $user->can('media.delete');
    }

    /** Browse the library from inside an editor. */
    public function pick(User $user): bool
    {
        return $user->can('media.view') || $this->isEditing($user);
    }

    /** Upload from inside an editor. */
    public function upload(User $user): bool
    {
        return $user->can('media.create') || $this->isEditing($user);
    }

    private function isEditing(User $user): bool
    {
        foreach (self::EDITING as $permission) {
            if ($user->can($permission)) {
                return true;
            }
        }

        return false;
    }
}
