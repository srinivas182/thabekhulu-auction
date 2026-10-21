<?php

namespace App\Domains\Theme\Support;

/**
 * The colour pairs every theme must pass (WCAG 2.2 AA): 4.5:1 for text,
 * 3:1 for buttons, focus rings and other interface parts. Includes the
 * fixed status colours against the chosen backgrounds.
 */
final class ThemeChecks
{
    /** @var list<array{0: string, 1: string, 2: float, 3: string}> foreground, background, minimum, label */
    private const PAIRS = [
        ['text', 'background', 4.5, 'Text on the page background'],
        ['text', 'surface', 4.5, 'Text on cards and panels'],
        ['muted_text', 'background', 4.5, 'Secondary text on the page background'],
        ['muted_text', 'surface', 4.5, 'Secondary text on cards and panels'],
        ['primary_foreground', 'primary', 4.5, 'Button text on primary buttons'],
        ['primary', 'background', 3.0, 'Primary buttons against the page'],
        ['accent', 'background', 3.0, 'Accent and focus ring against the page'],
        ['accent', 'surface', 3.0, 'Accent and focus ring on cards'],
    ];

    /**
     * @param  array<string, mixed>  $values
     * @return list<array{mode: string, field: string, label: string, foreground: string, background: string, ratio: float, minimum: float, pass: bool, suggestion: string|null}>
     */
    public static function run(array $values): array
    {
        $results = [];

        foreach (ThemePalette::MODES as $mode) {
            /** @var array<string, string> $colours */
            $colours = $values[$mode];

            foreach (self::PAIRS as [$fg, $bg, $min, $label]) {
                $results[] = self::result($mode, $fg, $label, $colours[$fg], $colours[$bg], $min);
            }

            foreach (ThemePalette::STATUS[$mode] as $status => $colour) {
                foreach (['background', 'surface'] as $bg) {
                    $results[] = self::result($mode, $bg, "Fixed “{$status}” badge colour on ".($bg === 'surface' ? 'cards' : 'the page background'), $colour, $colours[$bg], 4.5, suggestFor: 'background');
                }
            }
        }

        return $results;
    }

    /**
     * @param  array<string, mixed>  $values
     * @return list<array{mode: string, field: string, label: string, foreground: string, background: string, ratio: float, minimum: float, pass: bool, suggestion: string|null}>
     */
    public static function failures(array $values): array
    {
        return array_values(array_filter(self::run($values), fn (array $r): bool => ! $r['pass']));
    }

    /**
     * @return array{mode: string, field: string, label: string, foreground: string, background: string, ratio: float, minimum: float, pass: bool, suggestion: string|null}
     */
    private static function result(string $mode, string $field, string $label, string $fg, string $bg, float $min, string $suggestFor = 'foreground'): array
    {
        $ratio = Contrast::ratio($fg, $bg);
        $pass = $ratio >= $min;

        return [
            'mode' => $mode,
            'field' => $field,
            'label' => $label,
            'foreground' => $fg,
            'background' => $bg,
            'ratio' => round($ratio, 2),
            'minimum' => $min,
            'pass' => $pass,
            // For fixed status colours the background is what must change.
            'suggestion' => $pass ? null : ($suggestFor === 'background'
                ? Contrast::suggest($bg, $fg, $min)
                : Contrast::suggest($fg, $bg, $min)),
        ];
    }
}
