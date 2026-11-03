<?php

use App\Enums\GarageType;
use App\Parcels\BldgDescDecoder;

// NOTE: these codes come from the relay spec's code tables. Live NJ data was
// unreachable from the build sandbox, so the "50+ real codes sampled from the
// Morris import" set is a follow-up: run `piu:parcel-stats --county=MORRIS
// --sample-bldg=60` after the first import and extend this dataset.

dataset('decodable', [
    'spec example' => ['1.5SSTL2AG', 1.5, 'ST', 'Stone', 'L', 'Colonial', 2, GarageType::Attached],
    'frame colonial' => ['2SFL2AG', 2.0, 'F', 'Frame', 'L', 'Colonial', 2, GarageType::Attached],
    'brick rancher no garage' => ['1SBR', 1.0, 'B', 'Brick', 'R', 'Rancher', null, null],
    'aluminum split, unattached' => ['2SALS1UG', 2.0, 'AL', 'Aluminum siding', 'S', 'Split level', 1, GarageType::Unattached],
    'stucco split level' => ['2SSS', 2.0, 'S', 'Stucco', 'S', 'Split level', null, null],
    'structural steel colonial' => ['1SSSL', 1.0, 'SS', 'Structural steel', 'L', 'Colonial', null, null],
    'stone rancher' => ['1SSTR', 1.0, 'ST', 'Stone', 'R', 'Rancher', null, null],
    'stucco rancher' => ['1SSR', 1.0, 'S', 'Stucco', 'R', 'Rancher', null, null],
    'concrete block bi-level' => ['2SCB2', 2.0, 'CB', 'Concrete block', '2', 'Bi-level', null, null],
    'reinforced concrete commercial' => ['3SRCA', 3.0, 'RC', 'Reinforced concrete', 'A', 'Commercial', null, null],
    'wood tri-level' => ['2SW3', 2.0, 'W', 'Wood', '3', 'Tri-level', null, null],
    'metal industrial' => ['1SMB', 1.0, 'M', 'Metal', 'B', 'Industrial', null, null],
    'raised rancher 1 car' => ['1.5SFZ1AG', 1.5, 'F', 'Frame', 'Z', 'Raised rancher', 1, GarageType::Attached],
    'dutch colonial' => ['2SFD', 2.0, 'F', 'Frame', 'D', 'Dutch Colonial', null, null],
    'english tudor' => ['2.5SBE2UG', 2.5, 'B', 'Brick', 'E', 'English Tudor', 2, GarageType::Unattached],
    'twin' => ['2SALT', 2.0, 'AL', 'Aluminum siding', 'T', 'Twin', null, null],
    'row home' => ['3SBW', 3.0, 'B', 'Brick', 'W', 'Row home', null, null],
    'duplex' => ['2SFX', 2.0, 'F', 'Frame', 'X', 'Duplex', null, null],
    'apartments' => ['4SBC', 4.0, 'B', 'Brick', 'C', 'Apartments', null, null],
    'mobile home' => ['1SMM', 1.0, 'M', 'Metal', 'M', 'Mobile home', null, null],
    'other' => ['1SFO', 1.0, 'F', 'Frame', 'O', 'Other', null, null],
    'lowercase + spaces' => [' 2sfl 2ag ', 2.0, 'F', 'Frame', 'L', 'Colonial', 2, GarageType::Attached],
    'three car' => ['2SFL3AG', 2.0, 'F', 'Frame', 'L', 'Colonial', 3, GarageType::Attached],
]);

test('decodes BLDG_DESC codes', function (string $raw, float $stories, string $extCode, string $ext, string $styleCode, string $style, ?int $cars, ?GarageType $type) {
    $d = (new BldgDescDecoder)->decode($raw);

    expect($d)->not->toBeNull()
        ->and($d->stories)->toBe($stories)
        ->and($d->exteriorCode)->toBe($extCode)
        ->and($d->exterior)->toBe($ext)
        ->and($d->styleCode)->toBe($styleCode)
        ->and($d->style)->toBe($style)
        ->and($d->garageCars)->toBe($cars)
        ->and($d->garageType)->toBe($type);
})->with('decodable');

test('never guesses: undecodable values return null', function (?string $raw) {
    expect((new BldgDescDecoder)->decode($raw))->toBeNull();
})->with([null, '', 'NOT A CODE', 'SFL', '2FL', '2SQL', '2SF', '2SFL2XG', '2SFL2', '2SFLL', '0SFL', '2SSTQ']);

test('renders the plain-English display', function () {
    expect((new BldgDescDecoder)->decode('1.5SSTL2AG')->display())
        ->toBe('1.5-story stone Colonial, 2-car attached garage')
        ->and((new BldgDescDecoder)->decode('1SBR')->display())
        ->toBe('1-story brick Rancher');
});
