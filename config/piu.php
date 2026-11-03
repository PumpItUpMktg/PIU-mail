<?php

return [
    'parcels' => [
        // NJOGIS "Parcels and MOD-IV Composite" hosted FeatureServer layer.
        'layer_url' => env('NJ_PARCELS_LAYER_URL', 'https://services2.arcgis.com/XVOqAjTOJ5P6ngMu/arcgis/rest/services/Parcels_Composite_NJ_WM/FeatureServer/0'),
        'page_size' => (int) env('NJ_PARCELS_PAGE_SIZE', 2000),
        'http_timeout' => (int) env('NJ_PARCELS_HTTP_TIMEOUT', 120),
        'county_field' => 'COUNTY',

        // PROP_CLASS whitelist for the importer. Empty = import every class
        // (the default: retain everything). Fallback if statewide all-class
        // volume proves impractical: set to "2" (residential only).
        'import_classes' => array_values(array_filter(array_map('trim', explode(',', (string) env('NJ_PARCELS_IMPORT_CLASSES', ''))))),

        // Default app filter: prop_class = 2 and dwell_units <= this.
        'residential_max_dwell' => (int) env('PARCELS_RESIDENTIAL_MAX_DWELL', 1),
    ],

    'zcta' => [
        'layer_url' => env('ZCTA_LAYER_URL', 'https://tigerweb.geo.census.gov/arcgis/rest/services/TIGERweb/PUMA_TAD_TAZ_UGA_ZCTA/MapServer/1'),
        'page_size' => 200,
    ],

    // All 21 NJ counties: name (uppercase, no "County") => state+county FIPS.
    'nj_counties' => [
        'ATLANTIC' => '34001', 'BERGEN' => '34003', 'BURLINGTON' => '34005',
        'CAMDEN' => '34007', 'CAPE MAY' => '34009', 'CUMBERLAND' => '34011',
        'ESSEX' => '34013', 'GLOUCESTER' => '34015', 'HUDSON' => '34017',
        'HUNTERDON' => '34019', 'MERCER' => '34021', 'MIDDLESEX' => '34023',
        'MONMOUTH' => '34025', 'MORRIS' => '34027', 'OCEAN' => '34029',
        'PASSAIC' => '34031', 'SALEM' => '34033', 'SOMERSET' => '34035',
        'SUSSEX' => '34037', 'UNION' => '34039', 'WARREN' => '34041',
    ],
];
