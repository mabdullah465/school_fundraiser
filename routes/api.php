<?php

use App\Http\Controllers\Api\V1\Storefront\DonationSettingsController;
use App\Http\Controllers\Api\V1\Storefront\SchoolController;
use App\Http\Controllers\Webhooks\ShopifyWebhookController;
use Illuminate\Support\Facades\Route;

/*
| Mounted under /api/v1 (see bootstrap/app.php).
|
| Only the endpoints Shopify talks to exist right now:
|   - storefront/*       public, read-only, called by the theme in the browser
|   - webhooks/shopify   server to server, HMAC verified
| Admin and school portal APIs are added later (see docs/05-api-reference.md).
*/

Route::prefix('storefront')
    ->middleware(['throttle:storefront', 'cache.headers:public;max_age=60;etag'])
    ->group(function () {
        Route::get('schools', [SchoolController::class, 'index']);
        Route::get('schools/{id}', [SchoolController::class, 'show']);
        Route::get('donation-settings', DonationSettingsController::class);
    });

Route::post('webhooks/shopify', ShopifyWebhookController::class)
    ->middleware('shopify.hmac')
    ->name('webhooks.shopify');
