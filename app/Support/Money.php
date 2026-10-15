<?php

namespace App\Support;

use InvalidArgumentException;

/**
 * Integer minor-unit helpers. Money is never a float in this codebase.
 */
final class Money
{
    private const ZERO_DECIMAL = ['BIF', 'CLP', 'DJF', 'GNF', 'JPY', 'KMF', 'KRW', 'MGA', 'PYG', 'RWF', 'UGX', 'VND', 'VUV', 'XAF', 'XOF', 'XPF'];

    public static function exponent(string $currency): int
    {
        return in_array(strtoupper($currency), self::ZERO_DECIMAL, true) ? 0 : 2;
    }

    /**
     * Convert a decimal string such as "49.99" (as Shopify sends it) to minor units.
     * Digits beyond the currency exponent are rounded half up.
     */
    public static function toMinor(string|int|float|null $amount, string $currency = 'USD'): int
    {
        if ($amount === null || $amount === '') {
            return 0;
        }

        $value = is_float($amount) ? number_format($amount, 6, '.', '') : (string) $amount;
        $negative = str_starts_with($value, '-');
        $value = ltrim($value, '+-');

        if (! preg_match('/^\d*(\.\d+)?$/', $value) || $value === '') {
            throw new InvalidArgumentException("Not a decimal amount: {$amount}");
        }

        [$whole, $fraction] = array_pad(explode('.', $value, 2), 2, '');
        $exponent = self::exponent($currency);
        $fraction = str_pad($fraction, $exponent + 1, '0');

        // whole * 10^exp + the kept fraction digits, rounded half up on the next digit.
        $kept = $exponent === 0 ? 0 : (int) substr($fraction, 0, $exponent);
        $nextDigit = (int) $fraction[$exponent];
        $minor = ((int) ($whole === '' ? '0' : $whole)) * (10 ** $exponent) + $kept + ($nextDigit >= 5 ? 1 : 0);

        return $negative ? -$minor : $minor;
    }

    /**
     * Integer division rounded half up (for non-negative numerators).
     */
    public static function divideRoundHalfUp(int $numerator, int $denominator): int
    {
        if ($denominator <= 0) {
            throw new InvalidArgumentException('Denominator must be positive.');
        }

        if ($numerator < 0) {
            return -self::divideRoundHalfUp(-$numerator, $denominator);
        }

        return intdiv(2 * $numerator + $denominator, 2 * $denominator);
    }
}
