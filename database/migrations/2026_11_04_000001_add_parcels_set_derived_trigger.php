<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Compute centroid and site_zip as each parcel row is written, instead of
     * follow-up UPDATEs. Each UPDATE left a dead copy of the row behind
     * (Morris measured 744 MB before compaction vs 359 MB after).
     */
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION parcels_set_derived() RETURNS trigger AS $$
            BEGIN
                IF NEW.geom IS NULL THEN
                    NEW.centroid := NULL;
                    NEW.site_zip := NULL;
                ELSIF TG_OP = 'INSERT'
                   OR NEW.geom IS DISTINCT FROM OLD.geom
                   OR NEW.centroid IS NULL
                   OR NEW.site_zip IS NULL THEN
                    NEW.centroid := ST_Centroid(NEW.geom);
                    NEW.site_zip := (
                        SELECT z.zcta5 FROM zctas z
                        WHERE ST_Covers(z.geom, NEW.centroid)
                        ORDER BY z.zcta5
                        LIMIT 1
                    );
                END IF;
                RETURN NEW;
            END;
            $$ LANGUAGE plpgsql;

            CREATE TRIGGER parcels_set_derived
                BEFORE INSERT OR UPDATE ON parcels
                FOR EACH ROW EXECUTE FUNCTION parcels_set_derived();
            SQL);
    }

    public function down(): void
    {
        DB::unprepared('DROP TRIGGER IF EXISTS parcels_set_derived ON parcels; DROP FUNCTION IF EXISTS parcels_set_derived();');
    }
};
