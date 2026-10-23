<?php

use App\Domains\Verification\Support\SaIdNumber;

it('validates South African ID numbers', function (string $id, bool $valid) {
    expect(SaIdNumber::isValid($id))->toBe($valid);
})->with([
    'valid' => ['8001015009087', true],
    'with spaces' => ['800101 5009 087', true],
    'bad check digit' => ['8001015009086', false],
    'impossible date' => ['8013015009087', false],
    'too short' => ['800101500908', false],
    'bad citizenship digit' => ['8001015009287', false],
]);

it('reads the date of birth, choosing the right century', function () {
    expect(SaIdNumber::dateOfBirth('8001015009087')->toDateString())->toBe('1980-01-01')
        ->and(SaIdNumber::dateOfBirth('0501015009084')->toDateString())->toBe('2005-01-01');
});
