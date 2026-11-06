<?php

namespace App\Http\Middleware;

use App\Models\Inquiry;
use App\Support\PermissionCatalog;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'auth' => [
                'user' => $request->user(),
            ],
            'isAdmin' => (bool) $request->user()?->isAdmin(),
            // Every permission this person holds (all of them for a super administrator); the sidebar and
            // buttons use it to hide what the server would refuse anyway.
            'permissions' => fn (): array => $this->permissionsFor($request),
            // Shown as a badge on "Inquiries"; evaluated lazily so guests and partial reloads skip the query.
            'unreadInquiries' => fn (): int => $request->user() ? Inquiry::query()->where('status', 'new')->count() : 0,
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
        ];
    }

    /**
     * @return array<int, string>
     */
    private function permissionsFor(Request $request): array
    {
        $user = $request->user();

        if ($user === null) {
            return [];
        }

        return $user->isAdmin()
            ? PermissionCatalog::all()
            : $user->getAllPermissions()->map(fn ($permission): string => (string) $permission->name)->values()->all();
    }
}
