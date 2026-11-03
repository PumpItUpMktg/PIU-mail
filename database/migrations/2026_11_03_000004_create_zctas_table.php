<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Census ZCTA polygons for NJ, used to assign parcels.site_zip. */
    public function up(): void
    {
        Schema::create('zctas', function (Blueprint $table) {
            $table->char('zcta5', 5)->primary();
            $table->string('source_vintage')->nullable();
            $table->timestamps();
        });

        DB::statement('ALTER TABLE zctas ADD COLUMN geom geometry(MultiPolygon, 4326) NOT NULL');
        DB::statement('CREATE INDEX zctas_geom_gist ON zctas USING GIST (geom)');
    }

    public function down(): void
    {
        Schema::dropIfExists('zctas');
    }
};
