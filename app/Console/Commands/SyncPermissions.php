<?php

namespace App\Console\Commands;

use App\Services\PermissionSync;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('permissions:sync')]
#[Description('Create any permissions (and the default Editor role) that the code defines but the database lacks')]
class SyncPermissions extends Command
{
    public function handle(PermissionSync $sync): int
    {
        $created = $sync->run();

        $this->components->info($created === 0 ? 'Permissions are up to date.' : "Created {$created} permission(s).");

        return self::SUCCESS;
    }
}
