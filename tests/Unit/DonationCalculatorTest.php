<?php

namespace Tests\Unit;

use App\Domain\Donations\DonationCalculator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class DonationCalculatorTest extends TestCase
{
    /** @return array<string, array{int, int, int}> base minor, bps, expected minor */
    public static function cases(): array
    {
        return [
            'the $200 / 10% example from the plan' => [20000, 1000, 2000],
            'the $600 / 30% Figma sample' => [60000, 3000, 18000],
            'rounds half up: 5c at 30% is 1.5c' => [5, 3000, 2],
            'rounds down below half: 4c at 30% is 1.2c' => [4, 3000, 1],
            'tiny base rounds to zero' => [1, 1000, 0],
            'zero base' => [0, 3000, 0],
            'zero rate' => [20000, 0, 0],
            'negative base is treated as zero' => [-500, 3000, 0],
            'fractional percent 12.5%' => [10000, 1250, 1250],
            '100%' => [12345, 10000, 12345],
        ];
    }

    #[DataProvider('cases')]
    public function test_amount(int $base, int $bps, int $expected): void
    {
        $this->assertSame($expected, (new DonationCalculator)->amount($base, $bps));
    }
}
