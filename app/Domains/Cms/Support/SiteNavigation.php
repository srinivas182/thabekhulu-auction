<?php

namespace App\Domains\Cms\Support;

use App\Domains\Cms\Models\MenuItem;
use App\Domains\Cms\Settings\HomeSettings;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * Header and footer menus for every public page, cached and cleared when
 * menus or home settings change.
 */
final class SiteNavigation
{
    private const KEY = 'site:navigation';

    /**
     * @return array{header: list<array{label: string, url: string, newTab: bool}>, footer: list<array{label: string, url: string, newTab: bool}>, ffc: string}
     */
    public static function get(): array
    {
        try {
            return Cache::rememberForever(self::KEY, fn (): array => [
                'header' => self::menu('header'),
                'footer' => self::menu('footer'),
                'ffc' => app(HomeSettings::class)->ffc_number,
            ]);
        } catch (Throwable) {
            return ['header' => [], 'footer' => [], 'ffc' => ''];
        }
    }

    public static function forget(): void
    {
        Cache::forget(self::KEY);
    }

    /** @return list<array{label: string, url: string, newTab: bool}> */
    private static function menu(string $menu): array
    {
        return array_values(MenuItem::where('menu', $menu)->orderBy('sort_order')->get()
            ->map(fn (MenuItem $i): array => ['label' => $i->label, 'url' => $i->url, 'newTab' => $i->new_tab])
            ->all());
    }
}
