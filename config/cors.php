<?php

/*
| Only the public storefront endpoints are meant to be called from a browser
| on another origin (the Shopify theme). Webhooks are server-to-server.
|
| CORS_ALLOWED_ORIGINS is a comma separated list, e.g.
|   https://my-store.myshopify.com,https://www.my-store.com
*/
return [
    'paths' => ['api/v1/storefront/*'],

    'allowed_methods' => ['GET', 'OPTIONS'],

    'allowed_origins' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('CORS_ALLOWED_ORIGINS', ''))
    ))),

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['Accept', 'Content-Type'],

    'exposed_headers' => [],

    'max_age' => 600,

    'supports_credentials' => false,
];
