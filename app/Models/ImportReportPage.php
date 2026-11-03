<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ImportReportPage extends Model
{
    protected $fillable = ['import_report_id', 'offset', 'stats'];

    protected function casts(): array
    {
        return ['stats' => 'array'];
    }
}
