<?php

namespace App\Domains\Cms\Settings;

use Spatie\LaravelSettings\Settings;

class HomeSettings extends Settings
{
    public string $hero_title;

    public string $hero_text;

    // Each step: ['title' => string, 'text' => string]. No @var docblock: the
    // settings package resolves property types from it and cannot parse shapes.
    public array $steps; // @phpstan-ignore missingType.iterableValue

    /** PPRA Fidelity Fund Certificate number shown in the trust band. Empty until supplied. */
    public string $ffc_number;

    public bool $show_calendar;

    /** ULID of the listing in the hero, or null for the text-only hero. */
    public ?string $featured_listing;

    public static function group(): string
    {
        return 'home';
    }
}
