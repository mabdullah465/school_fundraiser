<?php

namespace Tests\Unit;

use App\Support\Money;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class MoneyTest extends TestCase
{
    /** @return array<string, array{string|int|float|null, string, int}> */
    public static function amounts(): array
    {
        return [
            'dollars and cents' => ['49.99', 'USD', 4999],
            'whole dollars' => ['50', 'USD', 5000],
            'one decimal' => ['7.5', 'USD', 750],
            'zero' => ['0.00', 'USD', 0],
            'null' => [null, 'USD', 0],
            'empty' => ['', 'USD', 0],
            'negative' => ['-3.25', 'USD', -325],
            'rounds half up' => ['1.005', 'USD', 101],
            'rounds down' => ['1.004', 'USD', 100],
            'zero decimal currency' => ['1500', 'JPY', 1500],
            'leading dot' => ['.5', 'USD', 50],
            'float input' => [19.9, 'USD', 1990],
        ];
    }

    #[DataProvider('amounts')]
    public function test_to_minor(string|int|float|null $input, string $currency, int $expected): void
    {
        $this->assertSame($expected, Money::toMinor($input, $currency));
    }

    public function test_garbage_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Money::toMinor('12abc');
    }

    public function test_divide_round_half_up(): void
    {
        $this->assertSame(1, Money::divideRoundHalfUp(5, 10));
        $this->assertSame(0, Money::divideRoundHalfUp(4, 10));
        $this->assertSame(2, Money::divideRoundHalfUp(15, 10));
        $this->assertSame(-2, Money::divideRoundHalfUp(-15, 10));
    }
}
