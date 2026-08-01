<?php

return [
    'hash_key' => env('ANALYTICS_HASH_KEY', env('APP_KEY')),

    'country_header_names' => [
        'CF-IPCountry',
        'X-Vercel-IP-Country',
        'X-Appengine-Country',
        'CloudFront-Viewer-Country',
    ],

    'local_country_header_name' => 'X-LuxurrStay-Test-Country',

    'supported_country_names' => [
        'MA' => 'Morocco',
        'FR' => 'France',
        'ES' => 'Spain',
        'BE' => 'Belgium',
        'US' => 'United States',
        'GB' => 'United Kingdom',
        'DE' => 'Germany',
        'IT' => 'Italy',
        'NL' => 'Netherlands',
        'CA' => 'Canada',
    ],

    'unknown' => [
        'country_code' => null,
        'country_name' => 'Unknown',
        'country_source' => null,
    ],
];
