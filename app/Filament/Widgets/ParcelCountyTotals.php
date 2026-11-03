<?php

namespace App\Filament\Widgets;

use Filament\Widgets\Widget;
use Illuminate\Support\Facades\DB;

class ParcelCountyTotals extends Widget
{
    protected string $view = 'filament.widgets.parcel-county-totals';

    protected int|string|array $columnSpan = 'full';

    protected static bool $isDiscovered = false;

    /** @return array<int, object{county: string, total: int, residential: int}> */
    public function getTotals(): array
    {
        $max = (int) config('piu.parcels.residential_max_dwell', 1);

        return DB::select(<<<'SQL'
            SELECT county, count(*) AS total,
                   count(*) FILTER (WHERE prop_class = '2' AND (dwell_units IS NULL OR dwell_units <= ?)) AS residential
            FROM parcels GROUP BY county ORDER BY county
            SQL, [$max]);
    }
}
