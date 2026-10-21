<?php

namespace App\Domains\Theme\Support;

/**
 * WCAG 2.2 contrast maths, plus a "nearest passing colour" suggestion that
 * keeps the hue and saturation and only moves lightness.
 * resources/js/lib/contrast.ts mirrors this for instant feedback; the
 * server result is the one that decides.
 */
final class Contrast
{
    public static function ratio(string $foreground, string $background): float
    {
        $a = self::luminance($foreground);
        $b = self::luminance($background);

        return (max($a, $b) + 0.05) / (min($a, $b) + 0.05);
    }

    public static function luminance(string $hex): float
    {
        [$r, $g, $b] = self::rgb($hex);
        $linear = fn (float $c): float => $c <= 0.04045 ? $c / 12.92 : (($c + 0.055) / 1.055) ** 2.4;

        return 0.2126 * $linear($r) + 0.7152 * $linear($g) + 0.0722 * $linear($b);
    }

    /**
     * Closest colour to $foreground (same hue) that reaches $minimum against
     * $background. Moves away from the background's lightness.
     */
    public static function suggest(string $foreground, string $background, float $minimum): string
    {
        if (self::ratio($foreground, $background) >= $minimum) {
            return strtolower($foreground);
        }

        [$h, $s, $l] = self::hsl($foreground);
        $step = self::luminance($background) > 0.18 ? -0.005 : 0.005;

        for ($i = 0; $i <= 200; $i++) {
            $l = max(0.0, min(1.0, $l + $step));
            $candidate = self::fromHsl($h, $s, $l);

            if (self::ratio($candidate, $background) >= $minimum) {
                return $candidate;
            }

            if ($l <= 0.0 || $l >= 1.0) {
                break;
            }
        }

        return $step < 0 ? '#000000' : '#ffffff';
    }

    /** @return array{0: float, 1: float, 2: float} */
    private static function rgb(string $hex): array
    {
        $hex = ltrim($hex, '#');

        return [hexdec(substr($hex, 0, 2)) / 255, hexdec(substr($hex, 2, 2)) / 255, hexdec(substr($hex, 4, 2)) / 255];
    }

    /** @return array{0: float, 1: float, 2: float} */
    private static function hsl(string $hex): array
    {
        [$r, $g, $b] = self::rgb($hex);
        $max = max($r, $g, $b);
        $min = min($r, $g, $b);
        $l = ($max + $min) / 2;

        if ($max === $min) {
            return [0.0, 0.0, $l];
        }

        $d = $max - $min;
        $s = $l > 0.5 ? $d / (2 - $max - $min) : $d / ($max + $min);
        $h = match ($max) {
            $r => ($g - $b) / $d + ($g < $b ? 6 : 0),
            $g => ($b - $r) / $d + 2,
            default => ($r - $g) / $d + 4,
        };

        return [$h / 6, $s, $l];
    }

    private static function fromHsl(float $h, float $s, float $l): string
    {
        if ($s === 0.0) {
            $r = $g = $b = $l;
        } else {
            $q = $l < 0.5 ? $l * (1 + $s) : $l + $s - $l * $s;
            $p = 2 * $l - $q;
            $r = self::hue($p, $q, $h + 1 / 3);
            $g = self::hue($p, $q, $h);
            $b = self::hue($p, $q, $h - 1 / 3);
        }

        return sprintf('#%02x%02x%02x', (int) round($r * 255), (int) round($g * 255), (int) round($b * 255));
    }

    private static function hue(float $p, float $q, float $t): float
    {
        if ($t < 0) {
            $t += 1;
        }
        if ($t > 1) {
            $t -= 1;
        }

        return match (true) {
            $t < 1 / 6 => $p + ($q - $p) * 6 * $t,
            $t < 1 / 2 => $q,
            $t < 2 / 3 => $p + ($q - $p) * (2 / 3 - $t) * 6,
            default => $p,
        };
    }
}
