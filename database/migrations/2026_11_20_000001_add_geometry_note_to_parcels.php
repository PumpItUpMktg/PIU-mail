<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * null = full outline from the source. 'simplified' = the source could
     * only serve a generalized (~5 m) outline; 'unavailable' = it could not
     * serve an outline at all (attributes still imported).
     */
    public function up(): void
    {
        Schema::table('parcels', function (Blueprint $table) {
            $table->string('geometry_note', 16)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('parcels', function (Blueprint $table) {
            $table->dropColumn('geometry_note');
        });
    }
};
