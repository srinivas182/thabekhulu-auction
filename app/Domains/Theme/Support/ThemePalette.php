<?php

namespace App\Domains\Theme\Support;

/**
 * What the theme manager can change, and the Thabekhulu brand defaults.
 * Auction status colours are fixed and listed here only so contrast
 * checks can include them.
 */
final class ThemePalette
{
    public const SCOPES = ['website', 'admin'];

    public const MODES = ['light', 'dark'];

    /** @var array<string, string> colour key => label */
    public const COLOURS = [
        'primary' => 'Primary (buttons, headers)',
        'primary_foreground' => 'Text on primary',
        'accent' => 'Accent (premium, focus, highlights)',
        'background' => 'Page background',
        'surface' => 'Cards and panels',
        'text' => 'Text',
        'muted_text' => 'Secondary text',
        'border' => 'Borders',
    ];

    /** @var array<string, string> */
    public const RADII = ['sharp' => '0.125rem', 'soft' => '0.625rem', 'round' => '1rem'];

    /**
     * Curated font pairings: [label, display font, interface font].
     *
     * @var array<string, array{0: string, 1: string, 2: string}>
     */
    public const PAIRINGS = [
        'thabekhulu' => ['Thabekhulu (brand)', 'Gloock', 'Schibsted Grotesk Variable'],
        'classic' => ['Classic', 'Playfair Display', 'Source Sans 3'],
        'modern' => ['Modern', 'DM Serif Display', 'DM Sans'],
        'heritage' => ['Heritage', 'Libre Caslon Display', 'Libre Franklin'],
        'studio' => ['Studio', 'Instrument Serif', 'Instrument Sans'],
        'bold' => ['Bold', 'Bricolage Grotesque', 'Inter'],
        'geometric' => ['Geometric', 'Syne', 'Manrope'],
        'humanist' => ['Humanist', 'Lora', 'Nunito Sans'],
    ];

    /** Fixed auction status colours (WCAG AA on the default backgrounds). */
    public const STATUS = [
        'light' => ['Live' => '#2d7b4e', 'Closing soon' => '#946219', 'Processing' => '#2f6690', 'Sold' => '#14201c', 'Coming soon' => '#656e6a'],
        'dark' => ['Live' => '#5bbf85', 'Closing soon' => '#e3ae55', 'Processing' => '#7fb2da', 'Sold' => '#e8ece9', 'Coming soon' => '#93a09b'],
    ];

    /**
     * Brand defaults (docs/brand/tokens.css). Both scopes start identical.
     *
     * @return array{light: array<string, string>, dark: array<string, string>, fonts: string, radius: string, logo_light: string|null, logo_dark: string|null, favicon: string|null, favicon_apple: string|null}
     */
    public static function defaults(): array
    {
        return [
            'light' => [
                'primary' => '#173b33', 'primary_foreground' => '#f2f3ee', 'accent' => '#6e5ba8',
                'background' => '#f2f3ee', 'surface' => '#ffffff', 'text' => '#14201c',
                'muted_text' => '#5e6e68', 'border' => '#d5dbd7',
            ],
            'dark' => [
                'primary' => '#cfe3da', 'primary_foreground' => '#0e1d19', 'accent' => '#a595e0',
                'background' => '#0e1d19', 'surface' => '#15271f', 'text' => '#e8ece9',
                'muted_text' => '#a3b2ac', 'border' => '#2a3d36',
            ],
            'fonts' => 'thabekhulu',
            'radius' => 'soft',
            'logo_light' => null,
            'logo_dark' => null,
            'favicon' => null,
            'favicon_apple' => null,
        ];
    }
}
