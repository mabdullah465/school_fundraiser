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
    | App credentials (Dev Dashboard "Client ID" / "Client secret").
    | When SHOPIFY_ADMIN_TOKEN is empty, an Admin API token is obtained with the
    | client credentials grant using these two values and cached until it expires.
    */
    'client_id' => env('SHOPIFY_CLIENT_ID'),
    'client_secret' => env('SHOPIFY_CLIENT_SECRET'),

    /*
    | Used to verify the X-Shopify-Hmac-Sha256 header. Shopify signs app webhooks
    | with the app's client secret, so that is the default. Webhooks are rejected
    | when this is empty.
    */
    'webhook_secret' => env('SHOPIFY_WEBHOOK_SECRET') ?: env('SHOPIFY_CLIENT_SECRET'),

    /*
    | Name of the cart attribute the theme writes the selected school id into.
    | It arrives on the order as note_attributes[].name.
    */
    'school_attribute' => env('SHOPIFY_SCHOOL_ATTRIBUTE', 'school_id'),

    /*
    | Webhook topic => handler class. Anything not listed here is stored in the
    | webhook inbox and ignored.
    */
    /*
    | After an order is paid, look up its transactions in the Admin API to read the processing fee
    | (Shopify Payments only). Turn off to skip the extra API call.
    */
    'fetch_fees' => (bool) env('SHOPIFY_FETCH_FEES', true),

    'unregistered' => ['fulfillments/create', 'fulfillments/update', 'fulfillment_events/create'],

    'handlers' => [
        'orders/create' => Handlers\OrderUpsertHandler::class,
        'orders/updated' => Handlers\OrderUpsertHandler::class,
        'orders/paid' => Handlers\OrderUpsertHandler::class,
        'orders/cancelled' => Handlers\OrderCancelledHandler::class,
        'refunds/create' => Handlers\RefundCreatedHandler::class,
        // Shopify refuses the legacy fulfillments/* topics for this app even with the fulfilment scope,
        // so shipping state comes from the order topics instead (they carry fulfillment_status).
        'orders/fulfilled' => Handlers\OrderUpsertHandler::class,
        'orders/partially_fulfilled' => Handlers\OrderUpsertHandler::class,
        // Kept so delivery events are handled if they are ever enabled (see FulfillmentHandler).
        // Not registered by shopify:register-webhooks, which skips topics listed in 'unregistered'.
        'fulfillments/create' => Handlers\FulfillmentHandler::class,
        'fulfillments/update' => Handlers\FulfillmentHandler::class,
        'fulfillment_events/create' => Handlers\FulfillmentHandler::class,
    ],
];
