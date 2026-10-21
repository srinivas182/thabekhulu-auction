<?php

namespace App\Domains\Theme\Support;

use App\Domains\Theme\Models\ThemeVersion;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Reads the published theme for a scope. Cached forever and cleared on
 * every publish, so pages never query the database for their colours.
 */
final class ThemeRepository
{
    /**
     * @return array<string, mixed>
     */
    public static function published(string $scope): array
    {
        try {
            return Cache::rememberForever(self::key($scope), function () use ($scope): array {
                $version = ThemeVersion::where('scope', $scope)->where('status', 'published')->latest('id')->first();

                return array_replace_recursive(ThemePalette::defaults(), $version->values ?? []);
            });
        } catch (Throwable) {
            return ThemePalette::defaults(); // tables not migrated yet
        }
    }

    public static function forget(string $scope): void
    {
        Cache::forget(self::key($scope));
        Cache::forget(self::key($scope).':css');
    }

    public static function css(string $scope): string
    {
        return Cache::rememberForever(self::key($scope).':css', fn (): string => ThemeCompiler::css($scope, self::published($scope)));
    }

    /**
     * Back-office pages, and account settings opened by staff, use the
     * back-office theme; everything else uses the website theme.
     */
    public static function scopeFor(Request $request): string
    {
        $user = $request->user();

        return $request->is('admin', 'admin/*') || ($request->is('settings/*') && $user instanceof User && $user->isStaff())
            ? 'admin'
            : 'website';
    }

    /**
     * Font entries to load: the pairing, plus the brand display font for
     * the back office (which only changes its interface font).
     *
     * @return list<string>
     */
    public static function fontEntries(string $scope): array
    {
        $pairing = (string) self::published($scope)['fonts'];
        $entries = ["resources/css/fonts/{$pairing}.css"];

        if ($scope === 'admin' && $pairing !== 'thabekhulu') {
            $entries[] = 'resources/css/fonts/thabekhulu.css';
        }

        return $entries;
    }

    /**
     * @return array{logoLight: string|null, logoDark: string|null, favicon: string|null, faviconApple: string|null}
     */
    public static function assets(string $scope): array
    {
        $values = self::published($scope);
        $url = fn (mixed $path): ?string => is_string($path) && $path !== '' ? Storage::disk('public')->url($path) : null;

        return [
            'logoLight' => $url($values['logo_light'] ?? null),
            'logoDark' => $url($values['logo_dark'] ?? null),
            'favicon' => $url($values['favicon'] ?? null),
            'faviconApple' => $url($values['favicon_apple'] ?? null),
        ];
    }

    private static function key(string $scope): string
    {
        return "theme:{$scope}:published";
    }
}
