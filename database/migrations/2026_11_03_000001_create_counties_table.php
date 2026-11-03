<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Registry of NJ counties: import status, refresh and failure tracking.
        Schema::create('counties', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('name')->unique(); // uppercase, without "County" (MORRIS)
            $table->char('fips', 5)->unique(); // state+county FIPS (34027)
            $table->string('status')->default('not_loaded')->index();
            $table->timestamp('loaded_at')->nullable();
            $table->date('source_pub_date')->nullable(); // max PCL_PBDATE at last load
            $table->unsignedInteger('row_count')->default(0);
            $table->text('last_error')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('counties');
    }
};
