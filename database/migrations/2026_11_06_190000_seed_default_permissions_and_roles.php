<?php

use App\Enums\UserRole;
use App\Models\User;
use App\Services\PermissionSync;
use App\Support\PermissionCatalog;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Creates the permission list and the default "Editor" role, and gives every existing
     * non-administrator account that role, so nobody loses access when roles become configurable.
     */
    public function up(): void
    {
        (new PermissionSync)->run();

        User::query()
            ->where('role', UserRole::Editor->value)
            ->each(fn (User $user) => $user->assignRole(PermissionCatalog::EDITOR_ROLE));
    }

    public function down(): void
    {
        // The tables are dropped by the create_permission_tables migration.
    }
};
