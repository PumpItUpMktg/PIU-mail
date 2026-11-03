<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Global reference data (NOT tenant-scoped). Owner identity fields
     * (OWNER_NAME, ST_ADDRESS, CITY_STATE, ZIP_CODE, ZIP5, ZIP_PLUS4) are
     * never stored here, in columns or in source_attributes.
     */
    public function up(): void
    {
        Schema::create('parcels', function (Blueprint $table) {
            $table->ulid('id')->primary();

            // Identifiers
            $table->string('pcl_guid')->nullable();
            $table->string('pams_pin')->nullable()->index(); // NOT unique: source has duplicates
            $table->string('pin_nodup')->nullable();
            $table->string('gis_pin')->nullable();
            $table->string('cd_code')->nullable();
            $table->string('block')->nullable();
            $table->string('lot')->nullable();
            $table->string('qcode')->nullable();

            // Location
            $table->string('county')->index();
            $table->string('municipality')->nullable()->index();
            $table->string('site_address')->nullable();
            $table->char('site_zip', 5)->nullable()->index();

            // Classification
            $table->string('prop_class', 8)->nullable()->index();
            $table->string('prop_use')->nullable();
            $table->integer('dwell_units')->nullable();
            $table->integer('comm_units')->nullable();

            // Building
            $table->smallInteger('yr_constr')->nullable()->index();
            $table->string('bldg_desc_raw')->nullable();
            $table->decimal('stories', 4, 2)->nullable();
            $table->string('exterior_code', 4)->nullable();
            $table->string('exterior')->nullable();
            $table->string('style_code', 2)->nullable()->index();
            $table->string('style')->nullable();
            $table->smallInteger('garage_cars')->nullable();
            $table->string('garage_type', 16)->nullable();
            $table->string('bldg_class')->nullable(); // raw; no official decode table found

            // Land
            $table->string('land_desc_raw')->nullable();
            $table->decimal('calc_acre', 12, 4)->nullable()->index();

            // Values: comparable only within one municipality (towns reassess in different years).
            $table->bigInteger('land_val')->nullable();
            $table->bigInteger('imprvt_val')->nullable();
            $table->bigInteger('net_value')->nullable();
            $table->decimal('last_yr_tax', 14, 2)->nullable();

            // Sales
            $table->bigInteger('sale_price')->nullable();
            $table->boolean('sale_nominal')->default(false);
            $table->string('sales_code')->nullable();
            $table->string('deed_book')->nullable();
            $table->string('deed_page')->nullable();
            $table->date('deed_date')->nullable()->index();

            // Dates
            $table->date('pcl_last_upd')->nullable();
            $table->date('pcl_pub_date')->nullable();

            // Derived
            $table->boolean('owner_occupied')->nullable()->index();

            $table->jsonb('source_attributes');
            $table->string('source_layer_version')->nullable();
            $table->foreignUlid('last_import_report_id')->nullable()->constrained('import_reports')->nullOnDelete();
            // Page offset within that run: tells a retried page (same offset)
            // apart from a duplicate key on a different page.
            $table->unsignedInteger('last_import_offset')->nullable();
            $table->timestamp('imported_at')->nullable();
            $table->timestamps();
        });

        DB::statement('ALTER TABLE parcels ADD COLUMN geom geometry(MultiPolygon, 4326)');
        DB::statement('ALTER TABLE parcels ADD COLUMN centroid geometry(Point, 4326)');
        DB::statement('CREATE INDEX parcels_geom_gist ON parcels USING GIST (geom)');
        DB::statement('CREATE INDEX parcels_centroid_gist ON parcels USING GIST (centroid)');

        // Upsert keys: pcl_guid when present, otherwise (pams_pin, county, site_address).
        DB::statement('CREATE UNIQUE INDEX parcels_pcl_guid_unique ON parcels (pcl_guid) WHERE pcl_guid IS NOT NULL');
        DB::statement('CREATE UNIQUE INDEX parcels_fallback_key_unique ON parcels (pams_pin, county, site_address) NULLS NOT DISTINCT WHERE pcl_guid IS NULL');
    }

    public function down(): void
    {
        Schema::dropIfExists('parcels');
    }
};
