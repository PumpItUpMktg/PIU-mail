<?php

use App\Parcels\OwnerFields;

test('strips every owner field, case-insensitively, and keeps the rest', function () {
    $in = [
        'OWNER_NAME' => 'X', 'st_address' => 'X', 'City_State' => 'X', 'ZIP_CODE' => 'X', 'ZIP5' => 'X', 'ZIP_PLUS4' => 'X',
        'PROP_LOC' => '12 MAIN ST', 'PAMS_PIN' => '1_2_3', 'FAC_NAME' => '',
    ];

    expect(OwnerFields::strip($in))->toBe(['PROP_LOC' => '12 MAIN ST', 'PAMS_PIN' => '1_2_3', 'FAC_NAME' => '']);
});
