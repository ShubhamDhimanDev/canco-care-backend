<?php

namespace App\Http\Controllers\Admin;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UserRequest;
use App\Models\User;
use App\Services\Audit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Role;

/**
 * Super administrators create and manage the other accounts ("sub-admins") here and choose their roles.
 * The routes sit behind `can:admin`.
 */
class UserController extends Controller
{
    public function index(): Response
    {
        $users = User::query()
            ->with('roles:id,name')
            ->orderByRaw('role = ? desc', [UserRole::Admin->value])
            ->orderBy('name')
            ->get()
            ->map(fn (User $user): array => $this->row($user));

        return Inertia::render('users/index', ['users' => $users]);
    }

    public function create(): Response
    {
        return Inertia::render('users/create', [
            'roles' => $this->roles(),
            'user' => null,
        ]);
    }

    public function store(UserRequest $request): RedirectResponse
    {
        $data = $request->validated();

        $user = new User(['name' => $data['name'], 'email' => $data['email'], 'password' => $data['password']]);
        // Created by a super administrator, so trusted: skip the email-verification step (mail may not
        // be configured yet). "role" is not mass-assignable, hence forceFill.
        $user->forceFill([
            'role' => ($data['is_super_admin'] ?? false) ? UserRole::Admin : UserRole::Editor,
            'email_verified_at' => now(),
        ])->save();

        $user->syncRoles($data['roles'] ?? []);

        if ($user->getRoleNames()->isNotEmpty()) {
            app(Audit::class)->record('updated', 'user', $user->id, $user->auditLabel(), null, ['roles' => $this->roleNames($user)]);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Account created. Share the password with them securely.')]);

        return to_route('users.index');
    }

    public function edit(User $user): Response
    {
        return Inertia::render('users/edit', [
            'roles' => $this->roles(),
            'user' => $this->row($user->load('roles:id,name')) + ['role_ids' => $user->roles->pluck('id')->all()],
        ]);
    }

    public function update(UserRequest $request, User $user): RedirectResponse
    {
        $data = $request->validated();
        $makeSuper = (bool) ($data['is_super_admin'] ?? false);

        if (! $makeSuper && $user->isAdmin()) {
            if ($user->is($request->user())) {
                return back()->withErrors(['is_super_admin' => __('You cannot remove your own super-administrator access. Ask another super administrator.')]);
            }

            if ($this->otherSuperAdmins($user) === 0) {
                return back()->withErrors(['is_super_admin' => __('There must be at least one super administrator.')]);
            }
        }

        $user->fill(['name' => $data['name'], 'email' => $data['email']]);

        if (filled($data['password'] ?? null)) {
            $user->password = $data['password'];
        }

        // A changed address is trusted (an administrator typed it), so it needs no new verification mail.
        $rolesBefore = $this->roleNames($user);

        $user->forceFill([
            'role' => $makeSuper ? UserRole::Admin : UserRole::Editor,
            'email_verified_at' => $user->isDirty('email') ? now() : $user->email_verified_at,
        ])->save();
        $user->syncRoles($data['roles'] ?? []);

        [$old, $new] = app(Audit::class)->diff(['roles' => $rolesBefore], ['roles' => $this->roleNames($user->refresh())]);

        if ($new !== []) {
            app(Audit::class)->record('updated', 'user', $user->id, $user->auditLabel(), $old, $new);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Account saved.')]);

        return to_route('users.edit', $user);
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        if ($user->is($request->user())) {
            return back()->withErrors(['delete' => __('You cannot delete your own account here.')]);
        }

        if ($user->isAdmin() && $this->otherSuperAdmins($user) === 0) {
            return back()->withErrors(['delete' => __('There must be at least one super administrator.')]);
        }

        $user->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Account deleted.')]);

        return to_route('users.index');
    }

    /**
     * @return array<int, string>
     */
    private function roleNames(User $user): array
    {
        return $user->roles()->pluck('name')->map(fn (mixed $name): string => (string) $name)->sort()->values()->all();
    }

    private function otherSuperAdmins(User $user): int
    {
        return User::query()->where('role', UserRole::Admin->value)->whereKeyNot($user->id)->count();
    }

    /**
     * @return array<int, array{id: int, name: string, permissions_count: int}>
     */
    private function roles(): array
    {
        return Role::query()
            ->withCount('permissions')
            ->orderBy('name')
            ->get()
            ->map(fn (Role $role): array => [
                'id' => (int) $role->id,
                'name' => $role->name,
                'permissions_count' => $role->permissions_count,
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function row(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'is_super_admin' => $user->isAdmin(),
            'roles' => $user->roles->pluck('name')->values()->all(),
            'two_factor' => $user->two_factor_confirmed_at !== null,
            'created_at' => $user->created_at?->toIso8601String(),
        ];
    }
}
