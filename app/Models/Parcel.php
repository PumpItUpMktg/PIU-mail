<?php

namespace App\Models;

use App\Enums\GarageType;
use App\Parcels\DecodedBldgDesc;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

/**
 * Global NJ parcel reference data (NOT tenant-scoped).
 *
 * No owner identity is ever stored here. Assessed values (land_val,
 * imprvt_val, net_value) are only comparable within one municipality.
 */
class Parcel extends Model
{
    use HasUlids;

    protected $guarded = ['id'];

    /** Spatial columns are written with raw SQL and never hydrated by default. */
    protected $hidden = ['geom', 'centroid'];

    protected function casts(): array
    {
        return [
            'source_attributes' => 'array',
            'yr_constr' => 'integer',
            'dwell_units' => 'integer',
            'comm_units' => 'integer',
            'stories' => 'float',
            'garage_cars' => 'integer',
            'garage_type' => GarageType::class,
            'calc_acre' => 'float',
            'land_val' => 'integer',
            'imprvt_val' => 'integer',
            'net_value' => 'integer',
            'last_yr_tax' => 'decimal:2',
            'sale_price' => 'integer',
            'sale_nominal' => 'boolean',
            'deed_date' => 'date',
            'pcl_last_upd' => 'date',
            'pcl_pub_date' => 'date',
            'owner_occupied' => 'boolean',
            'imported_at' => 'datetime',
        ];
    }

    /**
     * Default filter for every app query/view: residential class 2, and
     * single-family (dwell_units <= configured max; unknown dwell counts kept).
     *
     * @param  Builder<Parcel>  $query
     */
    public function scopeResidential(Builder $query, ?int $maxDwell = null): void
    {
        self::applyResidential($query, $maxDwell);
    }

    /**
     * The residential rule itself, usable on any builder (e.g. Filament's
     * nested filter queries, where local scopes aren't resolvable).
     *
     * @param  Builder<Parcel>|\Illuminate\Database\Query\Builder  $query
     */
    public static function applyResidential($query, ?int $maxDwell = null): void
    {
        $maxDwell ??= (int) config('piu.parcels.residential_max_dwell', 1);

        $query->where('prop_class', '2')
            ->where(fn ($q) => $q->whereNull('dwell_units')->orWhere('dwell_units', '<=', $maxDwell));
    }

    /**
     * "Built 1988 · 2-story frame Colonial · 2-car attached garage · 0.34 ac · Owner-occupied"
     */
    public function summary(): string
    {
        $parts = [];

        if ($this->yr_constr) {
            $parts[] = 'Built '.$this->yr_constr;
        }

        if ($this->stories !== null && $this->exterior !== null && $this->style !== null) {
            $parts[] = sprintf('%s-story %s %s', DecodedBldgDesc::formatStories($this->stories), strtolower($this->exterior), $this->style);
        }

        if ($this->garage_cars !== null && $this->garage_type !== null) {
            $parts[] = sprintf('%d-car %s garage', $this->garage_cars, $this->garage_type->value);
        }

        if ($this->calc_acre !== null) {
            $parts[] = number_format($this->calc_acre, 2).' ac';
        }

        if ($this->owner_occupied === true) {
            $parts[] = 'Owner-occupied';
        } elseif ($this->owner_occupied === false) {
            $parts[] = 'Not owner-occupied';
        }

        return implode(' · ', $parts);
    }
}
