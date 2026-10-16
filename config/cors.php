<?php

/*
| Public storefront endpoints may be called from any browser origin.
| Webhooks are server-to-server.
*/
return [
    'paths' => ['api/v1/storefront/*'],

    'allowed_methods' => ['GET', 'OPTIONS'],

    'allowed_origins' => ['*'],

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['Accept', 'Content-Type'],

    'exposed_headers' => [],

    'max_age' => 600,

    'supports_credentials' => false,
];
