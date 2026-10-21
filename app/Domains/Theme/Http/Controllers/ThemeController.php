<?php

namespace App\Domains\Theme\Http\Controllers;

use App\Domains\Theme\Actions\ManageTheme;
use App\Domains\Theme\Actions\StoreThemeAsset;
use App\Domains\Theme\Http\Requests\SaveThemeRequest;
use App\Domains\Theme\Models\ThemeVersion;
use App\Domains\Theme\Support\ThemeChecks;
use App\Domains\Theme\Support\ThemePalette;
use App\Domains\Theme\Support\ThemeRepository;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class ThemeController extends Controller
{
    public function edit(string $scope, ManageTheme $theme): Response
    {
        $published = ThemeRepository::published($scope);
        $draft = $theme->draft($scope);
        $working = $draft !== null ? array_replace_recursive($published, $draft->values) : $published;

        return Inertia::render('admin/theme/edit', [
            'scope' => $scope,
            'values' => $this->withUrls($working),
            'hasDraft' => $draft !== null,
            'checks' => ThemeChecks::run($working),
            'defaults' => ThemePalette::defaults(),
            'colours' => ThemePalette::COLOURS,
            'status' => ThemePalette::STATUS,
            'pairings' => collect(ThemePalette::PAIRINGS)->map(fn (array $p, string $key): array => [
                'key' => $key, 'label' => $p[0], 'display' => $p[1], 'ui' => $p[2],
            ])->values(),
            'radii' => array_keys(ThemePalette::RADII),
            'history' => ThemeVersion::with('publisher:id,first_name,last_name')
                ->where('scope', $scope)
                ->whereIn('status', ['published', 'archived'])
                ->latest('id')
                ->limit(20)
                ->get()
                ->map(fn (ThemeVersion $v): array => [
                    'id' => $v->id,
                    'live' => $v->status === 'published',
                    'publishedAt' => $v->published_at?->toIso8601String(),
                    'publishedBy' => $v->publisher?->name,
                    'note' => $v->note,
                ]),
        ]);
    }

    public function saveDraft(SaveThemeRequest $request, string $scope, #[CurrentUser] User $actor, ManageTheme $theme, StoreThemeAsset $assets): RedirectResponse
    {
        $draft = $theme->draft($scope);
        $current = $draft !== null ? $draft->values : ThemeRepository::published($scope);
        $values = array_replace($current, $request->themeValues());

        foreach ((array) $request->input('remove', []) as $kind) {
            $values[$kind] = null;
            if ($kind === 'favicon') {
                $values['favicon_apple'] = null;
            }
        }

        foreach (['logo_light', 'logo_dark', 'favicon'] as $kind) {
            $file = $request->file($kind);
            if ($file instanceof UploadedFile) {
                $values = array_replace($values, $assets->handle($scope, $kind, $file));
            }
        }

        $theme->saveDraft($actor, $scope, $values);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Draft saved. Publish it to make it live.')]);

        return back();
    }

    public function publish(string $scope, #[CurrentUser] User $actor, ManageTheme $theme): RedirectResponse
    {
        $theme->publish($actor, $scope);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Theme published.')]);

        return back();
    }

    public function discard(string $scope, ManageTheme $theme): RedirectResponse
    {
        $theme->discardDraft($scope);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Draft discarded.')]);

        return back();
    }

    public function restore(string $scope, ThemeVersion $version, #[CurrentUser] User $actor, ManageTheme $theme): RedirectResponse
    {
        $theme->restore($actor, $scope, $version);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Earlier version restored and published.')]);

        return back();
    }

    public function reset(string $scope, #[CurrentUser] User $actor, ManageTheme $theme): RedirectResponse
    {
        $theme->reset($actor, $scope);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Brand defaults restored and published.')]);

        return back();
    }

    /**
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    private function withUrls(array $values): array
    {
        foreach (['logo_light', 'logo_dark', 'favicon'] as $kind) {
            $path = $values[$kind] ?? null;
            $values["{$kind}_url"] = is_string($path) ? Storage::disk('public')->url($path) : null;
        }

        return $values;
    }
}
