<?php

namespace App\Parcels;

use Illuminate\Support\Facades\DB;

/**
 * Assigns parcels.site_zip by spatial join of the parcel centroid to Census
 * ZCTA polygons. (MOD-IV ZIP fields are the OWNER's mailing address, not the
 * property's.) The postal city comes later from Lob address verification.
 */
final class ZctaJoiner
{
    public function zctasLoaded(): bool
    {
        return DB::table('zctas')->exists();
    }

    /** Fills site_zip for one county; returns the count left without a ZIP. */
    public function assignCounty(string $county): int
    {
        DB::update(<<<'SQL'
            UPDATE parcels p
            SET site_zip = (
                SELECT z.zcta5 FROM zctas z
                WHERE ST_Covers(z.geom, p.centroid)
                ORDER BY z.zcta5
                LIMIT 1
            )
            WHERE p.county = ? AND p.centroid IS NOT NULL
            SQL, [$county]);

        return DB::table('parcels')->where('county', $county)->whereNull('site_zip')->count();
    }
}
