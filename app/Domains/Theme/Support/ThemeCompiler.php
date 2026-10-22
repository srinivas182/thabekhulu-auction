<?php

namespace App\Domains\Theme\Support;

/**
 * Turns theme values into CSS custom properties. Every value has been
 * validated (hex colours, known keys) before it reaches here, so the
 * output is safe to inline in the page head.
 */
final class ThemeCompiler
{
    /**
     * @param  array<string, mixed>  $values
     */
    public static function css(string $scope, array $values): string
    {
        /** @var array<string, string> $light */
        $light = $values['light'];
        $pairing = ThemePalette::PAIRINGS[(string) $values['fonts']] ?? ThemePalette::PAIRINGS['thabekhulu'];
        $brandDisplay = ThemePalette::PAIRINGS['thabekhulu'][1];

        $shared = [
            '--radius' => ThemePalette::RADII[(string) $values['radius']] ?? ThemePalette::RADII['soft'],
            // The back office changes only its interface font.
            '--font-display' => self::font($scope === 'admin' ? $brandDisplay : $pairing[1], 'Georgia, serif'),
            '--font-sans' => self::font($pairing[2], 'system-ui, sans-serif'),
            // Brand surfaces (hero bands) stay the light-mode primary in both modes.
            '--brand' => $light['primary'],
            '--brand-foreground' => $light['primary_foreground'],
        ];

        return ':root{'.self::declarations($shared + self::colours($light)).'}'
            .'.dark{'.self::declarations(self::colours($values['dark'])).'}';
    }

    /**
     * @param  array<string, string>  $c
     * @return array<string, string>
     */
    private static function colours(array $c): array
    {
        return [
            '--background' => $c['background'],
            '--foreground' => $c['text'],
            '--card' => $c['surface'],
            '--card-foreground' => $c['text'],
            '--popover' => $c['surface'],
            '--popover-foreground' => $c['text'],
            '--primary' => $c['primary'],
            '--primary-foreground' => $c['primary_foreground'],
            '--muted-foreground' => $c['muted_text'],
            '--border' => $c['border'],
            '--input' => $c['field_border'],
            '--ring' => $c['accent'],
            '--brand-accent' => $c['accent'],
            '--sidebar' => $c['surface'],
            '--sidebar-foreground' => $c['text'],
            '--sidebar-primary' => $c['primary'],
            '--sidebar-primary-foreground' => $c['primary_foreground'],
            '--sidebar-border' => $c['border'],
            '--sidebar-ring' => $c['accent'],
        ];
    }

    /**
     * @param  array<string, string>  $vars
     */
    private static function declarations(array $vars): string
    {
        $out = '';
        foreach ($vars as $name => $value) {
            $out .= "{$name}:{$value};";
        }

        return $out;
    }

    private static function font(string $family, string $fallback): string
    {
        return "'{$family}', {$fallback}";
    }
}
