<?php

namespace App\Domains\Cms\Support;

use App\Domains\Cms\Settings\HomeSettings;
use Illuminate\Support\Facades\Storage;

/**
 * Site photos that belong to no listing (home banner, sign-in pages), stored on the public disk
 * under brand/. The launch reset never removes them.
 */
final class BrandImages
{
    public const KINDS = ['hero' => 'hero_image', 'auth' => 'auth_image'];

    public static function url(string $kind): ?string
    {
        try {
            $path = app(HomeSettings::class)->{self::KINDS[$kind]};

            return $path !== null && Storage::disk('public')->exists($path) ? Storage::disk('public')->url($path) : null;
        } catch (\Throwable) {
            return null;
        }
    }

    /** Store new contents for a brand photo, replacing the old file. */
    public static function put(string $kind, string $contents, string $extension): string
    {
        $home = app(HomeSettings::class);
        $old = $home->{self::KINDS[$kind]};
        $path = 'brand/'.$kind.'-'.bin2hex(random_bytes(6)).'.'.strtolower($extension);
        Storage::disk('public')->put($path, $contents);
        $home->{self::KINDS[$kind]} = $path;
        $home->save();
        if ($old !== null && $old !== $path) {
            Storage::disk('public')->delete($old);
        }

        return $path;
    }

    public static function remove(string $kind): void
    {
        $home = app(HomeSettings::class);
        $old = $home->{self::KINDS[$kind]};
        $home->{self::KINDS[$kind]} = null;
        $home->save();
        if ($old !== null) {
            Storage::disk('public')->delete($old);
        }
    }
}
