<?php

namespace App\Http\Controllers\Api\V1\Storefront;

use App\Http\Controllers\Controller;
use App\Models\FundraisingRateVersion;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;

class DonationSettingsController extends Controller
{
    public const CACHE_KEY = 'storefront.donation-settings';

    /**
     * GET /api/v1/storefront/donation-settings
     *
     * Read-only view of the admin-configured rate for the theme to display.
     * The theme shows an estimate only. The ledger always uses the server-side rate at payment time.
     */
    public function __invoke(): JsonResponse
    {
        $settings = Cache::remember(
            self::CACHE_KEY,
            config('donations.settings_cache_seconds'),
            fn () => $this->build(),
        );

        return response()->json($settings);
    }

    /** @return array<string, mixed> */
    private function build(): array
    {
        $rate = FundraisingRateVersion::current();

        if ($rate === null) {
            return [
                'donation_enabled' => false,
                'percentage_basis_points' => 0,
                'calculation_basis' => config('donations.calculation_basis'),
                'included_in_price' => true,
                'settings_version' => null,
                'disclaimer' => null,
            ];
        }

        $percent = rtrim(rtrim(number_format($rate->school_bps / 100, 2, '.', ''), '0'), '.');

        return [
            'donation_enabled' => $rate->school_bps > 0,
            'percentage_basis_points' => $rate->school_bps,
            'calculation_basis' => $rate->calculation_basis,
            'included_in_price' => $rate->included_in_price,
            'settings_version' => $rate->version,
            'disclaimer' => "{$percent}% of your eligible merchandise purchase supports your selected school at no additional cost.",
        ];
    }
}
