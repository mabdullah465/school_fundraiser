<?php

namespace App\Integrations\Shopify\Handlers;

use App\Domain\Donations\Actions\AdjustDonationForRefund;
use App\Models\ShopifyOrder;
use App\Support\Money;

/**
 * refunds/create. Sums the eligible merchandise that was refunded and hands it to the ledger.
 * Shipping and tax refunds are ignored: they were never part of the donation base.
 */
class RefundCreatedHandler implements ShopifyWebhookHandler
{
    public function __construct(private readonly AdjustDonationForRefund $adjust) {}

    public function handle(string $topic, array $payload): void
    {
        $order = ShopifyOrder::where('shopify_order_id', (int) ($payload['order_id'] ?? 0))->first();

        if ($order === null) {
            // The refund arrived before we ever saw the order. Throwing makes the job retry later.
            throw new \RuntimeException("Refund {$payload['id']} received for unknown order {$payload['order_id']}.");
        }

        $eligibleLineIds = $order->items()->where('is_eligible', true)->pluck('shopify_line_item_id')->all();

        $refunded = 0;
        foreach ($payload['refund_line_items'] ?? [] as $line) {
            if (! in_array((int) $line['line_item_id'], $eligibleLineIds, true)) {
                continue;
            }

            $refunded += Money::toMinor(
                $line['subtotal_set']['shop_money']['amount'] ?? $line['subtotal'] ?? null,
                $order->currency,
            );
        }

        $this->adjust->handle($order, $refunded, (string) $payload['id']);
    }
}
