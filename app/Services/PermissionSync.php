<?php

namespace App\Services;

use App\Support\PermissionCatalog;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Makes the database match `PermissionCatalog`: creates missing permissions and, once, the default
 * "Editor" role. It never removes a permission or touches a role that already exists, so it is safe
 * to run on every deploy.
 */
class PermissionSync
{
    /**
     * @return int how many permissions were created
     */
    public function run(): int
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $created = 0;

        foreach (PermissionCatalog::all() as $name) {
            $permission = Permission::findOrCreate($name, 'web');

            if ($permission->wasRecentlyCreated) {
                $created++;
            }
        }

        $editorExists = Role::query()
            ->where('name', PermissionCatalog::EDITOR_ROLE)
            ->where('guard_name', 'web')
            ->exists();

        if (! $editorExists) {
            Role::create(['name' => PermissionCatalog::EDITOR_ROLE, 'guard_name' => 'web'])
                ->syncPermissions(PermissionCatalog::editorDefaults());
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return $created;
    }
}
