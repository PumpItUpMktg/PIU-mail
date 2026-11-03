<?php

namespace App\Models;

use App\Enums\ImportStatus;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One row per county import run. Holds counts only: never owner data.
 */
class ImportReport extends Model
{
    use HasUlids;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'status' => ImportStatus::class,
            'counts_by_class' => 'array',
            'skipped_classes' => 'array',
            'notes' => 'array',
            'source_pub_date_min' => 'date',
            'source_pub_date_max' => 'date',
            'source_last_upd_min' => 'date',
            'source_last_upd_max' => 'date',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    /** @return HasMany<ImportReportPage, $this> */
    public function pages(): HasMany
    {
        return $this->hasMany(ImportReportPage::class);
    }

    public function addNote(string $note): void
    {
        $notes = $this->notes ?? [];
        $notes[] = now()->toDateTimeString().' '.$note;
        $this->notes = $notes;
        $this->save();
    }

    public function decodeRate(): ?float
    {
        return $this->bldg_desc_present > 0
            ? round($this->bldg_desc_decoded / $this->bldg_desc_present * 100, 2)
            : null;
    }
}
