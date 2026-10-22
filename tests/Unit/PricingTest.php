<?php

use App\Domains\Listings\Enums\DealType;
use App\Domains\Listings\Support\Pricing;

it('suggests the commission minimum so the seller always nets their price', function () {
    expect(Pricing::floor(DealType::Commission, 50000, null, 0, 2000))->toBe(62500)
        ->and(Pricing::floor(DealType::Commission, 1800000, null, 0, 2000))->toBe(2250000)
        ->and(Pricing::floor(DealType::Commission, 100000, null, 0, 3300))->toBe(149254); // rounded up
});

it('sets the renovated minimum at seller price plus renovation', function () {
    expect(Pricing::floor(DealType::Renovated, null, 50000, 30000, 2000))->toBe(80000);
});

it('returns no floor until seller terms are entered', function () {
    expect(Pricing::floor(DealType::Commission, null, null, 0, 2000))->toBeNull()
        ->and(Pricing::floor(DealType::Renovated, null, null, 0, 2000))->toBeNull();
});

it('flags deposits above 10% of the minimum', function () {
    expect(Pricing::depositCheck(50000, 2300000))->toBe(['percent' => 2.2, 'aboveCap' => false])
        ->and(Pricing::depositCheck(50000, 350000)['aboveCap'])->toBeTrue()
        ->and(Pricing::depositCheck(50000, null))->toBe(['percent' => null, 'aboveCap' => false]);
});
