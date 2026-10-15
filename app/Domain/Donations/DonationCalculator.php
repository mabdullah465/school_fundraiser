<?php

namespace App\Domain\Donations;

use App\Support\Money;

/**
 * Pure maths, no I/O. This is the one place the donation amount is decided.
 */
final class DonationCalculator
{
    /**
     * @param  int  $baseMinor  Discounted eligible merchandise subtotal, excluding tax and shipping.
     * @param  int  $schoolBps  School share in basis points (3000 = 30%).
     */
    public function amount(int $baseMinor, int $schoolBps): int
    {
        if ($baseMinor <= 0 || $schoolBps <= 0) {
            return 0;
        }

        return Money::divideRoundHalfUp($baseMinor * $schoolBps, 10000);
    }
}
