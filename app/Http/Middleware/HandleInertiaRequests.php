<?php

namespace App\Http\Middleware;

use App\Domains\Access\Support\PermissionCatalogue;
use App\Domains\Theme\Support\ThemeRepository;
use App\Models\User;
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
            'theme' => [
                'scope' => ThemeRepository::scopeFor($request),
                ...ThemeRepository::assets(ThemeRepository::scopeFor($request)),
            ],
            'auth' => [
                'user' => $request->user(),
                // Permission names drive back-office navigation. Policies still
                // enforce access server-side; this only hides links.
                'permissions' => $request->user() instanceof User && $request->user()->isStaff()
                    ? ($request->user()->isSuperAdmin()
                        ? PermissionCatalogue::all()
                        : $request->user()->getAllPermissions()->pluck('name')->values()->all())
                    : [],
            ],
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
        ];
    }
}
