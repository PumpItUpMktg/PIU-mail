<?php

use App\Parcels\SourceValues;
use Carbon\CarbonImmutable;

test('year built is sanitized', function (mixed $raw, ?int $expected) {
    expect(SourceValues::yearBuilt($raw, 2026))->toBe($expected);
})->with([
    ['1988', 1988], [1700, 1700], ['2026', 2026],
    ['0', null], [0, null], ['1699', null], ['2027', null], ['', null], [null, null], ['abc', null], ['19 88', null],
]);

test('deed_date parses YYMMDD with a century pivot', function (?string $raw, ?string $expected) {
    $ref = CarbonImmutable::create(2026, 11, 3);

    expect(SourceValues::deedDate($raw, $ref)?->toDateString())->toBe($expected);
})->with([
    'this century' => ['190714', '2019-07-14'],
    'today' => ['261103', '2026-11-03'],
    'later this year flips to 1900s' => ['261231', '1926-12-31'],
    'last century' => ['991231', '1999-12-31'],
    'Y2K leap day' => ['000229', '2000-02-29'],
    '2025 had no Feb 29, 1925 neither' => ['250229', null],
    'bad month' => ['251301', null],
    'bad day' => ['250431', null],
    'blank' => ['', null],
    'null' => [null, null],
    'wrong length' => ['20190714', null],
    'MMDDYY-looking but invalid as YYMMDD' => ['123199', null],
]);

test('ArcGIS epoch-ms and text dates parse', function () {
    expect(SourceValues::sourceDate(1751241600000)?->toDateString())->toBe('2025-06-30')
        ->and(SourceValues::sourceDate('2025-06-30')?->toDateString())->toBe('2025-06-30')
        ->and(SourceValues::sourceDate('06/30/2025')?->toDateString())->toBe('2025-06-30')
        ->and(SourceValues::sourceDate('garbage'))->toBeNull()
        ->and(SourceValues::sourceDate(null))->toBeNull();
});

test('numeric helpers tolerate text', function () {
    expect(SourceValues::int('615,000'))->toBe(615000)
        ->and(SourceValues::int(''))->toBeNull()
        ->and(SourceValues::decimal('11234.56'))->toBe('11234.56')
        ->and(SourceValues::decimal('n/a'))->toBeNull();
});
