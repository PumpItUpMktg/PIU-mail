<?php

namespace App\Models;

use App\Enums\CountyStatus;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

/**
 * Registry of NJ counties for parcel import status / refresh / failures.
 */
class County extends Model
{
    use HasUlids;

    protected $fillable = ['name', 'fips', 'status', 'loaded_at', 'source_pub_date', 'row_count', 'last_error'];

    protected function casts(): array
    {
        return [
            'status' => CountyStatus::class,
            'loaded_at' => 'datetime',
            'source_pub_date' => 'date',
            'row_count' => 'integer',
        ];
    }

    public static function normalizeName(string $name): string
    {
        $name = strtoupper(trim($name));

        return trim((string) preg_replace('/\s+COUNTY$/', '', $name));
    }
}
