<?php

namespace App\Parcels;

use App\Models\County;

/**
 * Maps one GeoJSON feature from the NJ composite into parcels columns.
 *
 * Owner fields are read here, in memory only, to compute owner_occupied,
 * and are stripped before anything leaves this method.
 */
final class ParcelMapper
{
    public function __construct(private readonly BldgDescDecoder $decoder) {}

    /**
     * @param  array<string, mixed>  $feature
     */
    public function map(array $feature): MappedParcel
    {
        $raw = array_change_key_case((array) ($feature['properties'] ?? []), CASE_UPPER);

        // In-memory only: the owner's mailing street, for owner_occupied.
        $ownerOccupied = StreetNormalizer::ownerOccupied(
            is_scalar($raw['ST_ADDRESS'] ?? null) ? (string) $raw['ST_ADDRESS'] : null,
            is_scalar($raw['PROP_LOC'] ?? null) ? (string) $raw['PROP_LOC'] : null,
        );

        $attrs = OwnerFields::strip($raw);
        unset($raw);

        $bldgRaw = SourceValues::text($attrs['BLDG_DESC'] ?? null);
        $decoded = $this->decoder->decode($bldgRaw);
        $salePrice = SourceValues::int($attrs['SALE_PRICE'] ?? null);

        $columns = [
            'pcl_guid' => SourceValues::text($attrs['PCL_GUID'] ?? null),
            'pams_pin' => SourceValues::text($attrs['PAMS_PIN'] ?? null),
            'pin_nodup' => SourceValues::text($attrs['PIN_NODUP'] ?? null),
            'gis_pin' => SourceValues::text($attrs['GIS_PIN'] ?? null),
            'cd_code' => SourceValues::text($attrs['CD_CODE'] ?? null),
            'block' => SourceValues::text($attrs['PCLBLOCK'] ?? null),
            'lot' => SourceValues::text($attrs['PCLLOT'] ?? null),
            'qcode' => SourceValues::text($attrs['PCLQCODE'] ?? null),
            'county' => County::normalizeName((string) ($attrs['COUNTY'] ?? '')),
            'municipality' => self::upper($attrs['MUN_NAME'] ?? null),
            'site_address' => StreetNormalizer::tidy(self::str($attrs['PROP_LOC'] ?? null)),
            'prop_class' => self::upper($attrs['PROP_CLASS'] ?? null),
            'prop_use' => SourceValues::text($attrs['PROP_USE'] ?? null),
            'dwell_units' => SourceValues::int($attrs['DWELL'] ?? null),
            'comm_units' => SourceValues::int($attrs['COMM_DWELL'] ?? null),
            'yr_constr' => SourceValues::yearBuilt($attrs['YR_CONSTR'] ?? null),
            'bldg_desc_raw' => $bldgRaw,
            'stories' => $decoded?->stories,
            'exterior_code' => $decoded?->exteriorCode,
            'exterior' => $decoded?->exterior,
            'style_code' => $decoded?->styleCode,
            'style' => $decoded?->style,
            'garage_cars' => $decoded?->garageCars,
            'garage_type' => $decoded?->garageType?->value,
            'bldg_class' => SourceValues::text($attrs['BLDG_CLASS'] ?? null),
            'land_desc_raw' => SourceValues::text($attrs['LAND_DESC'] ?? null),
            'calc_acre' => SourceValues::decimal($attrs['CALC_ACRE'] ?? null),
            'land_val' => SourceValues::int($attrs['LAND_VAL'] ?? null),
            'imprvt_val' => SourceValues::int($attrs['IMPRVT_VAL'] ?? null),
            'net_value' => SourceValues::int($attrs['NET_VALUE'] ?? null),
            'last_yr_tax' => SourceValues::decimal($attrs['LAST_YR_TX'] ?? null),
            'sale_price' => $salePrice,
            'sale_nominal' => $salePrice === 1,
            'sales_code' => SourceValues::text($attrs['SALES_CODE'] ?? null),
            'deed_book' => SourceValues::text($attrs['DEED_BOOK'] ?? null),
            'deed_page' => SourceValues::text($attrs['DEED_PAGE'] ?? null),
            'deed_date' => SourceValues::deedDate($attrs['DEED_DATE'] ?? null)?->toDateString(),
            'pcl_last_upd' => SourceValues::sourceDate($attrs['PCLLASTUPD'] ?? null)?->toDateString(),
            'pcl_pub_date' => SourceValues::sourceDate($attrs['PCL_PBDATE'] ?? null)?->toDateString(),
            'owner_occupied' => $ownerOccupied,
        ];

        return new MappedParcel(
            columns: $columns,
            sourceAttributes: $attrs,
            geometryJson: self::polygonGeometry($feature['geometry'] ?? null),
            bldgDescPresent: $bldgRaw !== null,
            bldgDescDecoded: $decoded !== null,
        );
    }

    /** Only Polygon / MultiPolygon are accepted; anything else stores null. */
    private static function polygonGeometry(mixed $geometry): ?string
    {
        if (! is_array($geometry) || ! in_array($geometry['type'] ?? null, ['Polygon', 'MultiPolygon'], true)) {
            return null;
        }
        if (empty($geometry['coordinates'])) {
            return null;
        }

        return json_encode(['type' => $geometry['type'], 'coordinates' => $geometry['coordinates']]) ?: null;
    }

    private static function str(mixed $v): ?string
    {
        return is_scalar($v) ? (string) $v : null;
    }

    private static function upper(mixed $v): ?string
    {
        $t = SourceValues::text($v);

        return $t === null ? null : strtoupper($t);
    }
}
