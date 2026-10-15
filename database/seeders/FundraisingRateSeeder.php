<?php

namespace Database\Seeders;

use App\Enums\RateStatus;
use App\Models\FundraisingRateVersion;
use Illuminate\Database\Seeder;

/**
 * Rate history like the Figma "Fundraising Percentage" table: two closed rows, one active.
 */
class FundraisingRateSeeder extends Seeder
{
    public function run(): void
    {
        $basis = config('donations.calculation_basis');

        $rows = [
            ['donation-v1', 5000, RateStatus::Closed, now()->subDays(60), now()->subDays(40)],
            ['donation-v2', 4000, RateStatus::Closed, now()->subDays(40), now()->subDays(20)],
            ['donation-v3', 3000, RateStatus::Active, now()->subDays(20), null],
        ];

        foreach ($rows as [$version, $bps, $status, $effective, $closed]) {
            FundraisingRateVersion::updateOrCreate(['version' => $version], [
                'school_bps' => $bps,
                'calculation_basis' => $basis,
                'included_in_price' => true,
                'status' => $status,
                'active_slot' => $status === RateStatus::Active ? 1 : null,
                'effective_at' => $effective,
                'closed_at' => $closed,
            ]);
        }
    }
}
