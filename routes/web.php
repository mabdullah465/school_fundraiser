<?php

use App\Http\Controllers\DashboardController;
use Illuminate\Support\Facades\Route;

// Operational overview of what Shopify has sent and what was recorded. Protected, see config/dashboard.php.
Route::get('/', DashboardController::class)->middleware('dashboard')->name('dashboard');
