<?php

use App\Domains\Theme\Support\Contrast;
use App\Domains\Theme\Support\ThemeChecks;
use App\Domains\Theme\Support\ThemePalette;

it('calculates WCAG contrast ratios', function () {
    expect(round(Contrast::ratio('#000000', '#ffffff'), 2))->toBe(21.0)
        ->and(round(Contrast::ratio('#777777', '#ffffff'), 2))->toBe(4.48)
        ->and(round(Contrast::ratio('#ffffff', '#ffffff'), 2))->toBe(1.0);
});

it('suggests the nearest passing colour on light and dark backgrounds', function () {
    expect(Contrast::suggest('#777777', '#ffffff', 4.5))->toBe('#767676');

    $lighter = Contrast::suggest('#333333', '#000000', 4.5);
    expect(Contrast::ratio($lighter, '#000000'))->toBeGreaterThanOrEqual(4.5);
});

it('leaves passing colours unchanged', function () {
    expect(Contrast::suggest('#14201c', '#f2f3ee', 4.5))->toBe('#14201c');
});

it('passes every check with the brand defaults, including fixed status colours', function () {
    expect(ThemeChecks::failures(ThemePalette::defaults()))->toBe([])
        ->and(count(ThemeChecks::run(ThemePalette::defaults())))->toBe(40);
});
