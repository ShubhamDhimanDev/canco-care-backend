<?php

namespace App\Policies;

use App\Models\Inquiry;
use App\Models\User;

/**
 * Both roles (admin and editor) work the inquiry inbox. Donations are the admin-only area.
 */
class InquiryPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Inquiry $inquiry): bool
    {
        return true;
    }

    public function update(User $user, Inquiry $inquiry): bool
    {
        return true;
    }

    public function delete(User $user, Inquiry $inquiry): bool
    {
        return true;
    }
}
