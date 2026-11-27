<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Dwelling type stated in BLDG_DESC (SFD, 1 FAMILY, TOWNHOUSE, CONDO...). */
    public function up(): void
    {
        Schema::table('parcels', function (Blueprint $table) {
            $table->string('dwelling_type', 24)->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::table('parcels', function (Blueprint $table) {
            $table->dropColumn('dwelling_type');
        });
    }
};
