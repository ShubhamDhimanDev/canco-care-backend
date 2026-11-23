<?php

use App\Services\PermissionSync;
use App\Support\PermissionCatalog;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    /**
     * Creates the new `pages.update` permission and gives it to the default "Editor" role, so editors
     * can edit the pages the way they already edit the home page. Roles an administrator created
     * themselves are not touched: tick "Other pages" on them if wanted.
     */
    public function up(): void
    {
        (new PermissionSync)->run();

        Role::query()
            ->where('name', PermissionCatalog::EDITOR_ROLE)
            ->where('guard_name', 'web')
            ->first()
            ?->givePermissionTo('pages.update');

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Role::query()
            ->where('name', PermissionCatalog::EDITOR_ROLE)
            ->where('guard_name', 'web')
            ->first()
            ?->revokePermissionTo('pages.update');
    }
};
