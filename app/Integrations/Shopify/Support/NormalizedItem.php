<?php

namespace App\Integrations\Shopify\Support;

use App\Support\Money;

final class NormalizedItem
{
    public function __construct(
        public readonly int $lineItemId,
        public readonly ?int $productId,
        public readonly ?int $variantId,
        public readonly string $title,
        public readonly ?string $variantTitle,
        public readonly ?string $sku,
        public readonly int $quantity,
        public readonly int $unitPriceMinor,
        public readonly int $discountMinor,
        public readonly bool $isEligible,
    ) {}

    /**
     * @param  array<string, mixed>  $line
     */
    public static function fromPayload(array $line, string $currency): self
    {
        $quantity = (int) ($line['quantity'] ?? 1);
        $unit = Money::toMinor($line['price_set']['shop_money']['amount'] ?? $line['price'] ?? null, $currency);

        // Prefer per-allocation discounts (they include order-level discount codes). Fall back to total_discount.
        $allocations = $line['discount_allocations'] ?? [];
        $discount = $allocations !== []
            ? array_sum(array_map(
                fn ($a) => Money::toMinor($a['amount_set']['shop_money']['amount'] ?? $a['amount'] ?? null, $currency),
                $allocations,
            ))
            : Money::toMinor($line['total_discount'] ?? null, $currency);

        $productId = isset($line['product_id']) ? (int) $line['product_id'] : null;
        $excluded = $productId !== null && in_array((string) $productId, config('donations.excluded_product_ids'), true);

        return new self(
            lineItemId: (int) $line['id'],
            productId: $productId,
            variantId: isset($line['variant_id']) ? (int) $line['variant_id'] : null,
            title: (string) ($line['title'] ?? $line['name'] ?? 'Item'),
            variantTitle: $line['variant_title'] ?? null,
            sku: $line['sku'] ?? null,
            quantity: $quantity,
            unitPriceMinor: $unit,
            discountMinor: $discount,
            isEligible: ! ($line['gift_card'] ?? false) && ! $excluded,
        );
    }

    /** Line total after discounts, never negative. Shipping and tax are not part of a line. */
    public function netMinor(): int
    {
        return max(0, $this->unitPriceMinor * $this->quantity - $this->discountMinor);
    }
}
