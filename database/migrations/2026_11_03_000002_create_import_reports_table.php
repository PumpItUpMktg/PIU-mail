<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // One row per county import run. Never holds owner data.
        Schema::create('import_reports', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('county')->index();
            $table->string('trigger')->default('manual'); // manual | freshness | retry
            $table->string('status')->index();
            $table->string('source_layer_version')->nullable();
            $table->unsignedInteger('expected_count')->nullable();
            $table->unsignedInteger('fetched')->default(0);
            $table->unsignedInteger('inserted')->default(0);
            $table->unsignedInteger('updated')->default(0);
            $table->unsignedInteger('skipped_by_class')->default(0);
            $table->unsignedInteger('duplicates')->default(0);
            $table->unsignedInteger('null_year')->default(0);
            $table->unsignedInteger('null_geometry')->default(0);
            $table->unsignedInteger('no_zip')->default(0);
            $table->unsignedInteger('bldg_desc_present')->default(0);
            $table->unsignedInteger('bldg_desc_decoded')->default(0);
            $table->jsonb('counts_by_class')->default('{}');
            $table->jsonb('skipped_classes')->default('{}');
            $table->jsonb('notes')->default('[]');
            $table->date('source_pub_date_min')->nullable();
            $table->date('source_pub_date_max')->nullable();
            $table->date('source_last_upd_min')->nullable();
            $table->date('source_last_upd_max')->nullable();
            $table->text('error')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
        });

        // Per-page stats, keyed by offset so a retried page overwrites rather
        // than double-counts. Totals are summed into import_reports at finalize.
        Schema::create('import_report_pages', function (Blueprint $table) {
            $table->id();
            $table->foreignUlid('import_report_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('offset');
            $table->jsonb('stats');
            $table->timestamps();

            $table->unique(['import_report_id', 'offset']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('import_report_pages');
        Schema::dropIfExists('import_reports');
    }
};
