<?php

return [

    'turnstile' => [
        'key' => env('TURNSTILE_SITE_KEY'),
        'secret' => env('TURNSTILE_SECRET_KEY'),
        'ca_bundle' => env('TURNSTILE_CA_BUNDLE'),
    ],

    'ip2location' => [
        'key' => env('IP2LOCATION_API_KEY'),
    ],

];
