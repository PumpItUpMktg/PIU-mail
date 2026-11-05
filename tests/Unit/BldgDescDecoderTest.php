<?php

use App\Enums\GarageType;
use App\Parcels\BldgDescDecoder;

// Spec-table codes. Real Morris County codes are in the 'morris' dataset below.

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
})->with([null, '', 'NOT A CODE', 'SFL', '2FL', '2SQL', '2SFLL', '0SFL', '2SSTQ', 'CONDO', 'TYPE A', 'TYPE B']);

// The 60 most common BLDG_DESC values from the live Morris County import
// (2026-11-04), as `piu:parcel-stats --sample-bldg=60` listed them, with row
// counts. Expected: display text and how many parts stay undecoded.
// G{n} / G decode as an n-car garage / a garage with attachment unknown
// (owner-confirmed 2026-11-04). Other undocumented parts (G1X, 2CG, 2BG, OB,
// POOL, P, COL, 1FAM, F) stay undecoded and never break the documented parts.
dataset('morris', [
    ['1SF', '1-story frame', 0],                                       // 14550
    ['2SF', '2-story frame', 0],                                       // 9988
    ['2SF G2', '2-story frame, 2-car garage', 0],                                    // 7706
    ['1SF G1', '1-story frame, 1-car garage', 0],                                    // 4809
    ['1SF G2', '1-story frame, 2-car garage', 0],                                    // 4413
    ['2SF G1', '2-story frame, 1-car garage', 0],                                    // 3085
    ['2SF 2AG', '2-story frame, 2-car attached garage', 0],            // 2740
    ['1S F', '1-story frame', 0],                                      // 2613
    ['1.5SF', '1.5-story frame', 0],                                   // 2337
    ['CONDO', null, null],                                             // 2183
    ['2SFG2', '2-story frame, 2-car garage', 0],                                     // 1722
    ['2SF-L-2AG', '2-story frame Colonial, 2-car attached garage', 0], // 1684
    ['1S F G1', '1-story frame, 1-car garage', 0],                                   // 1475
    ['1SFG1', '1-story frame, 1-car garage', 0],                                     // 1148
    ['1.5SF G2', '1.5-story frame, 2-car garage', 0],                                // 1043
    ['1 SF', '1-story frame', 0],                                      // 921
    ['1SF G', '1-story frame, garage', 0],                                     // 916
    ['1SF G1X', '1-story frame', 1],                                   // 907
    ['2S F', '2-story frame', 0],                                      // 852
    ['2SF 2CG', '2-story frame', 1],                                   // 845
    ['1S F G2', '1-story frame, 2-car garage', 0],                                   // 845
    ['2SF G', '2-story frame, garage', 0],                                     // 779
    ['1.5SF G1', '1.5-story frame, 1-car garage', 0],                                // 757
    ['1SFG2', '1-story frame, 2-car garage', 0],                                     // 751
    ['2S F G2', '2-story frame, 2-car garage', 0],                                   // 736
    ['2S F G1', '2-story frame, 1-car garage', 0],                                   // 717
    ['1SCB', '1-story concrete block', 0],                             // 716
    ['2SFG1', '2-story frame, 1-car garage', 0],                                     // 708
    ['2SF-L-3AG', '2-story frame Colonial, 3-car attached garage', 0], // 707
    ['1SF G2X', '1-story frame', 1],                                   // 690
    ['2SF G3', '2-story frame, 3-car garage', 0],                                    // 667
    ['2SF-L-AG', '2-story frame Colonial, attached garage', 0],        // 599
    ['2SF-L-2AG POOL', '2-story frame Colonial, 2-car attached garage', 1], // 539
    ['2SF G2X', '2-story frame', 1],                                   // 535
    ['1.5 SF', '1.5-story frame', 0],                                  // 471
    ['1SB', '1-story brick', 0],                                       // 430
    ['1.5S F', '1.5-story frame', 0],                                  // 429
    ['1.5S F G1', '1.5-story frame, 1-car garage', 0],                               // 426
    ['1SF 2', '1-story frame Bi-level', 0],                            // 424
    ['1SFR', '1-story frame Rancher', 0],                              // 399
    ['2SF-L-3AG POOL', '2-story frame Colonial, 3-car attached garage', 1], // 393
    ['2S F 1FAM', '2-story frame', 1],                                 // 390
    ['1SF OB', '1-story frame', 1],                                    // 376
    ['2SF G2 P', '2-story frame, 2-car garage', 1],                                  // 374
    ['2SF L AG', '2-story frame Colonial, attached garage', 0],        // 365
    ['2SF G2 OB', '2-story frame, 2-car garage', 1],                                 // 363
    ['2SF L', '2-story frame Colonial', 0],                            // 362
    ['1SFG', '1-story frame, garage', 0],                                      // 355
    ['2 SF', '2-story frame', 0],                                      // 342
    ['2SF-G2', '2-story frame, 2-car garage', 0],                                    // 317
    ['2SF/2CG', '2-story frame', 1],                                   // 298
    ['TYPE A', null, null],                                            // 294
    ['TYPE B', null, null],                                            // 292
    ['2SF G1 OB', '2-story frame, 1-car garage', 1],                                 // 289
    ['2SF COL', '2-story frame', 1],                                   // 279
    ['1SF S', '1-story frame Split level', 0],                         // 278
    ['1SF-R-2AG', '1-story frame Rancher, 2-car attached garage', 0],  // 277
    ['1.5S-F-F', '1.5-story frame', 1],                                // 276
    ['2SF-O-2BG', '2-story frame Other', 1],                           // 269
    ['2SF-2AG', '2-story frame, 2-car attached garage', 0],            // 264
]);

test('decodes the real Morris County codes', function (string $raw, ?string $display, ?int $unknown) {
    $d = (new BldgDescDecoder)->decode($raw);

    if ($display === null) {
        expect($d)->toBeNull();

        return;
    }

    expect($d)->not->toBeNull()
        ->and($d->display())->toBe($display)
        ->and($d->unknownTokens)->toBe($unknown)
        ->and($d->complete())->toBe($unknown === 0);
})->with('morris');

test('loose codes keep the documented parts and count the rest as unknown', function () {
    $d = (new BldgDescDecoder)->decode('2SFL2XG');

    expect($d->display())->toBe('2-story frame Colonial')
        ->and($d->garageType)->toBeNull()
        ->and($d->unknownTokens)->toBe(1);
});

test('renders the plain-English display', function () {
    expect((new BldgDescDecoder)->decode('1.5SSTL2AG')->display())
        ->toBe('1.5-story stone Colonial, 2-car attached garage')
        ->and((new BldgDescDecoder)->decode('1SBR')->display())
        ->toBe('1-story brick Rancher');
});

test('G garages: count known, attachment unknown', function () {
    $d = (new BldgDescDecoder)->decode('2SF G2');

    expect($d->garageCars)->toBe(2)
        ->and($d->garageType)->toBe(GarageType::Unknown)
        ->and($d->garageDisplay())->toBe('2-car garage')
        ->and((new BldgDescDecoder)->decode('1SFG')->garageDisplay())->toBe('garage')
        ->and((new BldgDescDecoder)->decode('1SF G1X')->garageType)->toBeNull();
});

test('semicolons and commas separate parts too', function () {
    expect((new BldgDescDecoder)->decode('2SF;L,2AG')->display())->toBe('2-story frame Colonial, 2-car attached garage');
});
