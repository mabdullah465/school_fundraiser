<?php

namespace App\Integrations\Shopify\Fixtures;

use App\Support\Money;

/**
 * Builds Shopify-shaped webhook payloads for local testing, seeders and automated tests.
 * Shape follows the Shopify order / refund webhook payloads, trimmed to the fields we read.
 */
final class FakeShopifyPayload
{
    /**
     * @param  array{
     *   id?: int, number?: int, school?: string|null, school_name?: string|null,
     *   financial_status?: string, fulfillment_status?: string|null,
     *   items?: list<array{title?: string, price?: string, quantity?: int, discount?: string, product_id?: int, gift_card?: bool}>,
     *   shipping?: string, tax?: string, cancelled_at?: string|null, currency?: string, customer?: string
     * }  $options
     * @return array<string, mixed>
     */
    public static function order(array $options = []): array
    {
        $id = $options['id'] ?? random_int(5_000_000_000_000, 5_999_999_999_999);
        $number = $options['number'] ?? random_int(1001, 9999);
        $currency = $options['currency'] ?? 'USD';
        $items = $options['items'] ?? [['title' => 'Team T-Shirt', 'price' => '50.00', 'quantity' => 1]];
        $customer = $options['customer'] ?? 'Allison Vetrovs';

        $lines = [];
        $gross = 0;
        $discounts = 0;

        foreach (array_values($items) as $i => $item) {
            $price = Money::toMinor($item['price'] ?? '10.00', $currency);
            $quantity = $item['quantity'] ?? 1;
            $discount = Money::toMinor($item['discount'] ?? '0.00', $currency);
            $gross += $price * $quantity;
            $discounts += $discount;

            $lines[] = [
                'id' => $id * 10 + $i + 1,
                'product_id' => $item['product_id'] ?? 7_000_000_000_000 + $i,
                'variant_id' => 8_000_000_000_000 + $i,
                'title' => $item['title'] ?? 'Item '.($i + 1),
                'variant_title' => 'Default',
                'sku' => 'SKU-'.($i + 1),
                'quantity' => $quantity,
                'price' => self::decimal($price),
                'gift_card' => $item['gift_card'] ?? false,
                'total_discount' => self::decimal($discount),
                'discount_allocations' => $discount > 0
                    ? [['amount' => self::decimal($discount), 'discount_application_index' => 0]]
                    : [],
            ];
        }

        $shipping = Money::toMinor($options['shipping'] ?? '7.79', $currency);
        $tax = Money::toMinor($options['tax'] ?? '0.00', $currency);

        $noteAttributes = [];
        if (! empty($options['school'])) {
            $noteAttributes[] = ['name' => config('shopify.school_attribute'), 'value' => $options['school']];
            $noteAttributes[] = ['name' => 'school_name', 'value' => $options['school_name'] ?? 'Display name only'];
        }

        return [
            'id' => $id,
            'name' => '#'.$number,
            'email' => 'allison.vetrovs@example.test',
            'created_at' => now()->toIso8601String(),
            'currency' => $currency,
            'financial_status' => $options['financial_status'] ?? 'paid',
            'fulfillment_status' => $options['fulfillment_status'] ?? null,
            'cancelled_at' => $options['cancelled_at'] ?? null,
            'total_price' => self::decimal($gross - $discounts + $shipping + $tax),
            'subtotal_price' => self::decimal($gross - $discounts),
            'total_discounts' => self::decimal($discounts),
            'total_tax' => self::decimal($tax),
            'total_shipping_price_set' => ['shop_money' => ['amount' => self::decimal($shipping), 'currency_code' => $currency]],
            'payment_gateway_names' => [$options['gateway'] ?? 'shopify_payments'],
            'tax_lines' => $tax > 0 ? [[
                'title' => 'State Tax',
                'rate' => 0.0825,
                'price' => self::decimal($tax),
                'price_set' => ['shop_money' => ['amount' => self::decimal($tax), 'currency_code' => $currency]],
            ]] : [],
            'note_attributes' => $noteAttributes,
            'customer' => ['first_name' => explode(' ', $customer)[0], 'last_name' => explode(' ', $customer)[1] ?? ''],
            'shipping_address' => [
                'name' => $customer,
                'address1' => '53C, 14th street',
                'city' => 'Empire',
                'province' => 'Empire state',
                'country' => 'United States',
                'zip' => '10001',
            ],
            'line_items' => $lines,
        ];
    }

    /**
     * @param  list<array{line_item_id: int, subtotal: string}>  $refundLines
     * @return array<string, mixed>
     */
    public static function refund(int $orderId, int $refundId, array $refundLines): array
    {
        return [
            'id' => $refundId,
            'order_id' => $orderId,
            'created_at' => now()->toIso8601String(),
            'refund_line_items' => array_map(fn ($l) => [
                'line_item_id' => $l['line_item_id'],
                'quantity' => $l['quantity'] ?? 1,
                'subtotal' => $l['subtotal'],
            ], $refundLines),
        ];
    }

    /**
     * Signs a body the way Shopify does: base64(HMAC-SHA256(body, secret)).
     */
    public static function sign(string $body, string $secret): string
    {
        return base64_encode(hash_hmac('sha256', $body, $secret, true));
    }

    private static function decimal(int $minor): string
    {
        return number_format($minor / 100, 2, '.', '');
    }
}
