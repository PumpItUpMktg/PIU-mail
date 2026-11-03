<?php

use App\Parcels\StreetNormalizer;

test('owner-occupied compares normalized streets', function (?string $owner, ?string $site, ?bool $expected) {
    expect(StreetNormalizer::ownerOccupied($owner, $site))->toBe($expected);
})->with([
    'same, different spelling' => ['12 N Main St.', '12 NORTH MAIN STREET', true],
    'extra whitespace' => ['3 Oak Ln', '3 OAK  LANE', true],
    'avenue/ave' => ['5 Park Avenue', '5 PARK AVE', true],
    'different house' => ['14 N MAIN ST', '12 N MAIN ST', false],
    'po box' => ['PO BOX 999', '14 N MAIN ST', false],
    'owner blank (redacted)' => ['', '16 N MAIN ST', null],
    'owner null' => [null, '16 N MAIN ST', null],
    'site blank' => ['16 N MAIN ST', '  ', null],
]);
