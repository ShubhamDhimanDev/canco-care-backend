<?php

namespace App\Policies;

use App\Models\Inquiry;
use App\Models\User;

/**
 * The inquiry inbox is governed by the `inquiries.*` permissions (see PermissionCatalog).
 * Exporting is checked by the controller through `inquiries.export`.
 */
class InquiryPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('inquiries.view');
    }

    public function view(User $user, Inquiry $inquiry): bool
    {
        return $user->can('inquiries.view');
    }

    public function update(User $user, Inquiry $inquiry): bool
    {
        return $user->can('inquiries.update');
    }

    public function delete(User $user, Inquiry $inquiry): bool
    {
        return $user->can('inquiries.delete');
    }
}
