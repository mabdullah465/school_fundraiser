<?php

namespace App\Integrations\Shopify\Support;

use App\Support\Money;
use Carbon\CarbonImmutable;

/**
 * A Shopify order webhook payload reduced to what this backend needs, with all money in minor units.
 * Shopify payloads are loose (strings, nulls, optional *_set blocks), so all parsing lives here.
 */
final class NormalizedOrder
{
    private const PAID_STATUSES = ['paid', 'partially_refunded'];

    /**
     * @param  list<NormalizedItem>  $items
     * @param  array<string, mixed>|null  $shippingAddress
     */
    public function __construct(
        public readonly int $shopifyOrderId,
        public readonly ?string $orderNumber,
        public readonly ?string $customerName,
        public readonly ?string $customerEmail,
        public readonly ?array $shippingAddress,
        public readonly string $currency,
        public readonly int $subtotalMinor,
        public readonly int $discountMinor,
        public readonly int $shippingMinor,
        public readonly int $taxMinor,
        public readonly int $totalMinor,
        public readonly ?string $financialStatus,
        public readonly ?string $fulfillmentStatus,
        public readonly ?string $schoolAttribute,
        public readonly ?CarbonImmutable $placedAt,
        public readonly ?CarbonImmutable $cancelledAt,
        public readonly array $items,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function fromPayload(array $payload): self
    {
        $currency = strtoupper((string) ($payload['currency'] ?? 'USD'));

        $address = $payload['shipping_address'] ?? null;
        $customer = $payload['customer'] ?? [];
        $customerName = $address['name']
            ?? trim(($customer['first_name'] ?? '').' '.($customer['last_name'] ?? ''))
            ?: null;

        $items = [];
        foreach ($payload['line_items'] ?? [] as $line) {
            $items[] = NormalizedItem::fromPayload($line, $currency);
        }

        $itemsGross = array_sum(array_map(fn (NormalizedItem $i) => $i->unitPriceMinor * $i->quantity, $items));

        return new self(
            shopifyOrderId: (int) $payload['id'],
            orderNumber: $payload['name'] ?? null,
            customerName: $customerName,
            customerEmail: $payload['email'] ?? $customer['email'] ?? null,
            shippingAddress: $address,
            currency: $currency,
            subtotalMinor: $itemsGross,
            discountMinor: Money::toMinor($payload['total_discounts'] ?? null, $currency),
            shippingMinor: Money::toMinor(self::shopMoney($payload, 'total_shipping_price_set'), $currency),
            taxMinor: Money::toMinor($payload['total_tax'] ?? null, $currency),
            totalMinor: Money::toMinor($payload['total_price'] ?? null, $currency),
            financialStatus: $payload['financial_status'] ?? null,
            fulfillmentStatus: $payload['fulfillment_status'] ?? null,
            schoolAttribute: self::schoolAttribute($payload),
            placedAt: isset($payload['created_at']) ? CarbonImmutable::parse($payload['created_at']) : null,
            cancelledAt: isset($payload['cancelled_at']) ? CarbonImmutable::parse($payload['cancelled_at']) : null,
            items: $items,
        );
    }

    public function isPaid(): bool
    {
        return in_array($this->financialStatus, self::PAID_STATUSES, true);
    }

    /**
     * The cart attribute the theme wrote. Only this id is trusted. Names and rates in the order are ignored.
     *
     * @param  array<string, mixed>  $payload
     */
    private static function schoolAttribute(array $payload): ?string
    {
        $wanted = config('shopify.school_attribute');

        foreach ($payload['note_attributes'] ?? [] as $attribute) {
            if (($attribute['name'] ?? null) === $wanted) {
                $value = trim((string) ($attribute['value'] ?? ''));

                return $value === '' ? null : $value;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $row
     */
    public static function shopMoney(array $row, string $setKey): ?string
    {
        return $row[$setKey]['shop_money']['amount'] ?? null;
    }
}
