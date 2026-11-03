<?php

namespace App\Filament\Resources\Parcels\Schemas;

use App\Models\Parcel;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\KeyValueEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ParcelInfolist
{
    public static function configure(Schema $schema): Schema
    {
        $t = fn (string $name, ?string $label = null) => TextEntry::make($name)->label($label ?? str($name)->headline())->placeholder('—');

        return $schema->columns(1)->components([
            Section::make('Summary')->schema([
                TextEntry::make('summary')->hiddenLabel()->state(fn (Parcel $record) => $record->summary() ?: '—')->size('lg'),
            ]),
            Section::make('Location')->columns(3)->schema([
                $t('site_address', 'Site address'), $t('municipality'), $t('county'), $t('site_zip', 'Site ZIP (ZCTA)'),
            ]),
            Section::make('Identifiers')->columns(4)->collapsible()->schema([
                $t('pcl_guid', 'PCL GUID'), $t('pams_pin', 'PAMS PIN'), $t('pin_nodup', 'PIN (no dup)'), $t('gis_pin', 'GIS PIN'),
                $t('cd_code', 'CD code'), $t('block'), $t('lot'), $t('qcode', 'Qual. code'),
            ]),
            Section::make('Building')->columns(4)->schema([
                $t('prop_class', 'Property class'), $t('prop_use', 'Property use'), $t('dwell_units', 'Dwellings'), $t('comm_units', 'Comm. dwellings'),
                $t('yr_constr', 'Year built'), $t('bldg_desc_raw', 'BLDG_DESC'), $t('stories'), $t('exterior'),
                $t('style'), $t('garage_cars', 'Garage cars'), $t('garage_type', 'Garage type'), $t('bldg_class', 'BLDG_CLASS (raw)'),
                IconEntry::make('owner_occupied')->boolean()->placeholder('Unknown'),
            ]),
            Section::make('Land & values')
                ->description('Assessed values are only comparable within a single municipality.')
                ->columns(4)->schema([
                    $t('land_desc_raw', 'Land desc.'), $t('calc_acre', 'Acres'),
                    $t('land_val', 'Land value')->money('USD', decimalPlaces: 0),
                    $t('imprvt_val', 'Improvement value')->money('USD', decimalPlaces: 0),
                    $t('net_value', 'Net value')->money('USD', decimalPlaces: 0),
                    $t('last_yr_tax', 'Last year tax')->money('USD'),
                ]),
            Section::make('Sale')->columns(4)->schema([
                $t('sale_price', 'Sale price')->money('USD', decimalPlaces: 0),
                IconEntry::make('sale_nominal')->label('Nominal ($1)')->boolean(),
                $t('sales_code', 'Sales code'), $t('deed_book', 'Deed book'), $t('deed_page', 'Deed page'),
                $t('deed_date', 'Deed date')->date(),
            ]),
            Section::make('Source')->columns(4)->collapsible()->schema([
                $t('pcl_last_upd', 'PCLLASTUPD')->date(), $t('pcl_pub_date', 'PCL_PBDATE')->date(),
                $t('source_layer_version', 'Layer version'), $t('imported_at', 'Imported')->dateTime(),
            ]),
            Section::make('All source attributes (owner fields excluded)')->collapsed()->schema([
                KeyValueEntry::make('source_attributes')->hiddenLabel(),
            ]),
        ]);
    }
}
