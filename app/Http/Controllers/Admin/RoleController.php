<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\RoleRequest;
use App\Services\Audit;
use App\Support\PermissionCatalog;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Role;

/**
 * Roles are named bundles of permissions; sub-admins get one or more of them (see UserController).
 * Super administrators only: the routes sit behind `can:admin`.
 */
class RoleController extends Controller
{
    public function index(): Response
    {
        $roles = Role::query()
            ->withCount(['users', 'permissions'])
            ->orderBy('name')
            ->get()
            ->map(fn (Role $role): array => [
                'id' => $role->id,
                'name' => $role->name,
                'users_count' => $role->users_count,
                'permissions_count' => $role->permissions_count,
            ]);

        return Inertia::render('roles/index', [
            'roles' => $roles,
            'totalPermissions' => count(PermissionCatalog::all()),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('roles/create', [
            'groups' => $this->groups(),
            'role' => ['id' => null, 'name' => '', 'permissions' => PermissionCatalog::editorDefaults(), 'users_count' => 0],
        ]);
    }

    public function store(RoleRequest $request): RedirectResponse
    {
        $role = Role::query()->create(['name' => $request->validated('name'), 'guard_name' => 'web']);
        $role->syncPermissions($request->validated('permissions') ?? []);

        app(Audit::class)->record('created', 'role', $role->id, $role->name, null, [
            'name' => $role->name,
            'permissions' => $this->permissionNames($role),
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Role created.')]);

        return to_route('roles.index');
    }

    public function edit(Role $role): Response
    {
        return Inertia::render('roles/edit', [
            'groups' => $this->groups(),
            'role' => [
                'id' => $role->id,
                'name' => $role->name,
                'permissions' => $role->permissions()->pluck('name')->values()->all(),
                'users_count' => $role->users()->count(),
            ],
        ]);
    }

    public function update(RoleRequest $request, Role $role): RedirectResponse
    {
        $before = ['name' => $role->name, 'permissions' => $this->permissionNames($role)];

        $role->update(['name' => $request->validated('name')]);
        $role->syncPermissions($request->validated('permissions') ?? []);

        [$old, $new] = app(Audit::class)->diff($before, ['name' => $role->name, 'permissions' => $this->permissionNames($role->refresh())]);

        if ($new !== []) {
            app(Audit::class)->record('updated', 'role', $role->id, $role->name, $old, $new);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Role saved.')]);

        return to_route('roles.edit', $role);
    }

    public function destroy(Role $role): RedirectResponse
    {
        $count = $role->users()->count();
        $snapshot = ['name' => $role->name, 'permissions' => $this->permissionNames($role), 'people_who_had_it' => $count];
        $role->delete();

        app(Audit::class)->record('deleted', 'role', $role->id, $snapshot['name'], $snapshot);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => $count === 0
                ? __('Role deleted.')
                : trans_choice('Role deleted. :count person no longer has it.|Role deleted. :count people no longer have it.', $count),
        ]);

        return to_route('roles.index');
    }

    /**
     * @return array<int, string>
     */
    private function permissionNames(Role $role): array
    {
        return $role->permissions()->pluck('name')->map(fn (mixed $name): string => (string) $name)->sort()->values()->all();
    }

    /**
     * @return list<array{key: string, label: string, description: string, permissions: list<array{name: string, label: string}>}>
     */
    private function groups(): array
    {
        $groups = [];

        foreach (PermissionCatalog::groups() as $key => $group) {
            $groups[] = [
                'key' => $key,
                'label' => $group['label'],
                'description' => $group['description'],
                'permissions' => array_map(
                    fn (string $name, string $label): array => ['name' => $name, 'label' => $label],
                    array_keys($group['permissions']),
                    $group['permissions'],
                ),
            ];
        }

        return $groups;
    }
}
