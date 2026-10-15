<?php

use App\Integrations\Shopify\Handlers;

return [

    /*
    | The store this backend serves, e.g. "my-store.myshopify.com".
    */
    'store_domain' => env('SHOPIFY_STORE_DOMAIN'),

    /*
    | Pin the Admin API version. Bump deliberately, never implicitly.
    */
    'api_version' => env('SHOPIFY_API_VERSION', '2025-10'),

    /*
    | Custom app Admin API access token (shpat_...). Only needed for
    | shopify:register-webhooks and re-fetching orders.
    */
    'admin_token' => env('SHOPIFY_ADMIN_TOKEN'),

    /*
    | Used to verify the X-Shopify-Hmac-Sha256 header. For a custom app this is
    | the app's "API secret key". Webhooks are rejected when this is empty.
    */
    'webhook_secret' => env('SHOPIFY_WEBHOOK_SECRET'),

    /*
    | Name of the cart attribute the theme writes the selected school id into.
    | It arrives on the order as note_attributes[].name.
    */
    'school_attribute' => env('SHOPIFY_SCHOOL_ATTRIBUTE', 'school_id'),

    /*
    | Webhook topic => handler class. Anything not listed here is stored in the
    | webhook inbox and ignored.
    */
    'handlers' => [
        'orders/create' => Handlers\OrderUpsertHandler::class,
        'orders/updated' => Handlers\OrderUpsertHandler::class,
        'orders/paid' => Handlers\OrderUpsertHandler::class,
        'orders/cancelled' => Handlers\OrderCancelledHandler::class,
        'refunds/create' => Handlers\RefundCreatedHandler::class,
        'fulfillments/create' => Handlers\FulfillmentHandler::class,
        'fulfillments/update' => Handlers\FulfillmentHandler::class,
        'fulfillment_events/create' => Handlers\FulfillmentHandler::class,
    ],
];
