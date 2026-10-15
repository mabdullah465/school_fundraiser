<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // The theme calls these on every product and cart page view.
        RateLimiter::for('storefront', fn (Request $request) => Limit::perMinute(60)->by($request->ip()));
    }
}
